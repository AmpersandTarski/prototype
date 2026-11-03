<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\Exception\InvalidTokenException;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The door: a session takes its roles from the request instead of from a login (OK-32).
 *
 * An identity service in front of the application authenticates every visitor. For a person in a
 * browser, a reverse proxy (Caddy with forward_auth to oauth2-proxy) asks the identity service who
 * the visitor is and passes the names of his groups in a request header. For a machine, the request
 * carries a bearer token that the identity service issued with the client_credentials grant, and
 * the application checks that token itself. In both cases a group name or a permission equals the
 * name of a role in the script. The application learns which roles its visitor has, and need not
 * know who the visitor is.
 *
 * The door is off by default (`session.door: null`), and then nothing changes. With
 * `session.door: proxy` every request gets exactly the roles it presents:
 *
 *   - a bearer token in `Authorization`: the roles in its claim `scp` (or `scope`), after the
 *     signature, issuer, audience and expiry have been checked; a token that fails a check ends
 *     the request with 401;
 *   - otherwise the header named by `session.door.groupsHeader`: the roles named in it,
 *     comma-separated (Apache joins repeated header lines with ", ");
 *   - otherwise nothing: the session gets the role Anonymous, if the script declares it.
 *
 * Only roles that the script declares count, and never the roles of the framework itself
 * (ExecEngine, SYSTEM) or the wildcard Any. The header is believed because the proxy is the only
 * way in; `session.door.trustedProxies` narrows that further to the addresses of the proxy.
 */
final class Door
{
    public const SETTING_MODE = 'session.door';
    public const SETTING_GROUPS_HEADER = 'session.door.groupsHeader';
    public const SETTING_TOKEN_ISSUER = 'session.door.tokenIssuer';
    public const SETTING_TOKEN_AUDIENCE = 'session.door.tokenAudience';
    public const SETTING_TRUSTED_PROXIES = 'session.door.trustedProxies';

    public const MODE_PROXY = 'proxy';

    /**
     * Roles that no visitor can present: the framework's own roles and the wildcard.
     * Anonymous is given by the door itself, to a request that presents nothing.
     */
    public const ROLES_NEVER_PRESENTED = ['ExecEngine', 'SYSTEM', ProtoContext::ROLE_ANY, ProtoContext::ROLE_ANONYMOUS];

    /**
     * Seconds of clock difference tolerated when checking exp, nbf and iat of a token.
     */
    public const LEEWAY = 30;

    /**
     * Asymmetric algorithms only: a symmetric algorithm would let anyone who knows a public key
     * forge a token, and 'none' would let anyone forge one without a key.
     */
    public const ALLOWED_ALGORITHMS = ['RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'EdDSA'];

    public static function isEnabled(Settings $settings): bool
    {
        return $settings->get(self::SETTING_MODE) === self::MODE_PROXY;
    }

    /**
     * The roles that this request presents at the door.
     *
     * @param string[] $declaredRoles names of the roles that the script declares
     * @param DoorKeySet|null $keySet the keys of the identity service, needed only for a bearer token
     * @return string[] role names, without duplicates
     * @throws InvalidTokenException when the request carries a bearer token that fails a check
     */
    public static function rolesFromRequest(
        Settings $settings,
        array $serverParams,
        array $declaredRoles,
        ?DoorKeySet $keySet = null,
        ?LoggerInterface $logger = null
    ): array {
        $token = self::bearerToken($serverParams);
        if ($token !== null) {
            if ($keySet === null) {
                throw new InvalidTokenException("The application cannot check bearer tokens: no token issuer is configured");
            }
            $presented = self::rolesFromToken($token, $settings, $keySet, $logger);
        } else {
            $presented = self::rolesFromHeader($settings, $serverParams, $logger);
        }

        if ($presented === null) {
            // Nothing presented: the no-user role, when the script has one.
            return in_array(ProtoContext::ROLE_ANONYMOUS, $declaredRoles, true) ? [ProtoContext::ROLE_ANONYMOUS] : [];
        }

        return self::filterRoles($presented, $declaredRoles);
    }

    /**
     * Keep the presented names that the script declares as roles, minus the roles nobody presents.
     *
     * @param string[] $presented
     * @param string[] $declaredRoles
     * @return string[]
     */
    public static function filterRoles(array $presented, array $declaredRoles): array
    {
        $roles = array_filter(
            array_unique($presented),
            fn (string $name) => in_array($name, $declaredRoles, true)
                && !in_array($name, self::ROLES_NEVER_PRESENTED, true)
        );
        return array_values($roles);
    }

    /**
     * The bearer token in the Authorization header, or null when there is none.
     *
     * Apache passes the header to PHP only with `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`
     * in its site configuration; without a header that line yields an empty string.
     */
    public static function bearerToken(array $serverParams): ?string
    {
        $header = $serverParams['HTTP_AUTHORIZATION'] ?? $serverParams['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches) !== 1) {
            return null;
        }
        return $matches[1];
    }

    /**
     * The names in the groups header, or null when the request has no such header or comes from an
     * address that is not a trusted proxy.
     *
     * @return string[]|null
     */
    public static function rolesFromHeader(Settings $settings, array $serverParams, ?LoggerInterface $logger = null): ?array
    {
        $headerName = $settings->get(self::SETTING_GROUPS_HEADER, 'X-Auth-Request-Groups');
        $serverParam = 'HTTP_' . strtoupper(str_replace('-', '_', (string) $headerName));
        if (!array_key_exists($serverParam, $serverParams)) {
            return null;
        }

        if (!self::fromTrustedProxy($settings, $serverParams)) {
            $logger?->warning("Door: ignored the header {$headerName}, because the request does not come from a trusted proxy");
            return null;
        }

        $value = (string) $serverParams[$serverParam];
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $name) => $name !== ''));
    }

    /**
     * Whether the request comes from an address in `session.door.trustedProxies`.
     * Without that setting every address counts as trusted: the deployment then guarantees that the
     * proxy is the only way in.
     */
    public static function fromTrustedProxy(Settings $settings, array $serverParams): bool
    {
        $trusted = $settings->get(self::SETTING_TRUSTED_PROXIES);
        if ($trusted === null || $trusted === '' || $trusted === []) {
            return true;
        }
        if (is_string($trusted)) {
            $trusted = explode(',', $trusted);
        }

        $remote = $serverParams['REMOTE_ADDR'] ?? '';
        foreach ((array) $trusted as $range) {
            if (self::addressInRange((string) $remote, trim((string) $range))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether an IPv4 or IPv6 address lies in a range written as an address or as address/prefix.
     */
    public static function addressInRange(string $address, string $range): bool
    {
        [$subnet, $prefix] = array_pad(explode('/', $range, 2), 2, null);
        $addressBytes = @inet_pton($address);
        $subnetBytes = @inet_pton((string) $subnet);
        if ($addressBytes === false || $subnetBytes === false || strlen($addressBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $bits = strlen($addressBytes) * 8;
        $prefix = $prefix === null ? $bits : (int) $prefix;
        if ($prefix < 0 || $prefix > $bits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        if (substr($addressBytes, 0, $fullBytes) !== substr($subnetBytes, 0, $fullBytes)) {
            return false;
        }
        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($addressBytes[$fullBytes]) & $mask) === (ord($subnetBytes[$fullBytes]) & $mask);
    }

    /**
     * The roles in a bearer token, after the checks of signature, algorithm, issuer, audience and time.
     *
     * @return string[]
     * @throws InvalidTokenException
     */
    public static function rolesFromToken(string $token, Settings $settings, DoorKeySet $keySet, ?LoggerInterface $logger = null): array
    {
        $issuer = self::normaliseUrl($settings->get(self::SETTING_TOKEN_ISSUER));
        $audience = self::normaliseUrl($settings->get(self::SETTING_TOKEN_AUDIENCE));
        if ($issuer === '' || $audience === '') {
            throw new InvalidTokenException("The application cannot check bearer tokens: token issuer or audience is not configured");
        }

        $claims = self::decode($token, $keySet, $logger);

        if (self::normaliseUrl($claims->iss ?? '') !== $issuer) {
            throw new InvalidTokenException("The bearer token was not accepted: it comes from another issuer");
        }

        $audiences = is_array($claims->aud ?? null) ? $claims->aud : [$claims->aud ?? ''];
        $audiences = array_map(fn ($aud) => self::normaliseUrl($aud), $audiences);
        if (!in_array($audience, $audiences, true)) {
            throw new InvalidTokenException("The bearer token was not accepted: it is meant for another application");
        }

        if (!isset($claims->exp)) {
            throw new InvalidTokenException("The bearer token was not accepted: it has no expiry");
        }

        if (isset($claims->scp) && is_array($claims->scp)) {
            $roles = $claims->scp;
        } elseif (isset($claims->scope) && is_string($claims->scope)) {
            $roles = preg_split('/\s+/', trim($claims->scope), -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $roles = [];
        }

        return array_values(array_filter($roles, 'is_string'));
    }

    /**
     * Verify the signature and the times of a token with the keys of the identity service.
     * An unknown key id makes the key set fetch the keys once more, because the identity service may
     * have rotated its keys since they were cached.
     */
    private static function decode(string $token, DoorKeySet $keySet, ?LoggerInterface $logger): object
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY;
        try {
            $header = self::header($token);
            if (!in_array($header->alg ?? '', self::ALLOWED_ALGORITHMS, true)) {
                throw new InvalidTokenException("The bearer token was not accepted: its algorithm is not allowed");
            }

            $kid = $header->kid ?? null;
            $keys = $keySet->keys();
            if (is_string($kid) && !array_key_exists($kid, $keys)) {
                $keys = $keySet->keys(refresh: true);
            }

            return JWT::decode($token, $keys);
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (Throwable $e) {
            // The reason goes to the log; the visitor learns only the kind of failure.
            $logger?->notice("Door: bearer token refused: " . $e->getMessage());
            throw new InvalidTokenException("The bearer token was not accepted: " . self::reason($e), previous: $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    private static function header(string $token): object
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidTokenException("The bearer token was not accepted: it is not a JWT");
        }
        $header = json_decode(JWT::urlsafeB64Decode($parts[0]));
        if (!is_object($header)) {
            throw new InvalidTokenException("The bearer token was not accepted: its header cannot be read");
        }
        return $header;
    }

    private static function reason(Throwable $e): string
    {
        return match (true) {
            $e instanceof \Firebase\JWT\ExpiredException => 'it has expired',
            $e instanceof \Firebase\JWT\BeforeValidException => 'it is not valid yet',
            $e instanceof \Firebase\JWT\SignatureInvalidException => 'its signature does not match',
            default => 'it cannot be verified',
        };
    }

    private static function normaliseUrl(mixed $url): string
    {
        return is_string($url) ? rtrim(trim($url), '/') : '';
    }
}
