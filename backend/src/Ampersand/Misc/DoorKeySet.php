<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\Exception\InvalidTokenException;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The public keys with which the identity service signs its tokens, for the door (OK-32).
 *
 * The keys come from the JSON Web Key Set that the OpenID configuration of the issuer names
 * (`<issuer>/.well-known/openid-configuration`, field `jwks_uri`). They are cached in a file for
 * ten minutes, so that a request with a token costs no round trip to the identity service. A token
 * with an unknown key id asks for fresh keys, at most once a minute, so that a stream of forged
 * key ids cannot turn the application into a load on the identity service.
 */
class DoorKeySet
{
    public const CACHE_SECONDS = 600;
    public const MIN_REFRESH_SECONDS = 60;

    /**
     * @var Closure(string): string fetches a URL and returns its body
     */
    private Closure $fetch;

    public function __construct(
        private string $issuer,
        private string $cacheFile,
        ?Closure $fetch = null,
        private ?LoggerInterface $logger = null
    ) {
        $this->issuer = rtrim($issuer, '/');
        $this->fetch = $fetch ?? Closure::fromCallable([self::class, 'httpGet']);
    }

    public static function forIssuer(string $issuer, ?LoggerInterface $logger = null): self
    {
        $cacheFile = sys_get_temp_dir() . '/ampersand-door-jwks-' . hash('sha256', rtrim($issuer, '/')) . '.json';
        return new self($issuer, $cacheFile, null, $logger);
    }

    /**
     * @return array<string, Key> the keys, indexed by key id
     * @throws InvalidTokenException when no keys can be obtained
     */
    public function keys(bool $refresh = false): array
    {
        $cached = $this->readCache();
        $age = $cached === null ? PHP_INT_MAX : time() - $cached['fetchedAt'];

        $mustFetch = $cached === null
            || $age > self::CACHE_SECONDS
            || ($refresh && $age > self::MIN_REFRESH_SECONDS);

        if ($mustFetch) {
            try {
                $jwks = $this->fetchKeySet();
                $this->writeCache($jwks);
                $cached = ['fetchedAt' => time(), 'jwks' => $jwks];
            } catch (Throwable $e) {
                $this->logger?->error("Door: cannot fetch the keys of {$this->issuer}: " . $e->getMessage());
                if ($cached === null) {
                    throw new InvalidTokenException("The bearer token cannot be checked: the keys of the identity service are not available", previous: $e);
                }
                // Keep working with the keys we have.
            }
        }

        return JWK::parseKeySet($cached['jwks'], 'RS256');
    }

    private function fetchKeySet(): array
    {
        $configuration = json_decode(($this->fetch)($this->issuer . '/.well-known/openid-configuration'), true);
        if (!is_array($configuration) || !is_string($configuration['jwks_uri'] ?? null)) {
            throw new \RuntimeException("no jwks_uri in the OpenID configuration");
        }
        if (rtrim((string) ($configuration['issuer'] ?? ''), '/') !== $this->issuer) {
            throw new \RuntimeException("the OpenID configuration names another issuer");
        }

        $jwks = json_decode(($this->fetch)($configuration['jwks_uri']), true);
        if (!is_array($jwks) || !is_array($jwks['keys'] ?? null) || $jwks['keys'] === []) {
            throw new \RuntimeException("the key set has no keys");
        }
        return $jwks;
    }

    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($this->cacheFile), true);
        if (!is_array($data) || !is_int($data['fetchedAt'] ?? null) || !is_array($data['jwks'] ?? null)) {
            return null;
        }
        return $data;
    }

    private function writeCache(array $jwks): void
    {
        $tmp = $this->cacheFile . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode(['fetchedAt' => time(), 'jwks' => $jwks])) !== false) {
            @rename($tmp, $this->cacheFile);
        }
    }

    private static function httpGet(string $url): string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status !== 200) {
            throw new \RuntimeException("GET {$url} gave status {$status} {$error}");
        }
        return (string) $body;
    }
}
