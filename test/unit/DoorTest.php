<?php

/**
 * Standalone test for the door ({@see \Ampersand\Misc\Door} and {@see \Ampersand\Misc\DoorKeySet}, OK-32).
 *
 * The door decides the roles of a session from the request: from the groups header that a proxy
 * passes on, or from a bearer token that an identity service signed. These checks cover
 *
 *   - which names in the header become roles (declared ones only, never the framework's own),
 *   - what a request that presents nothing gets (Anonymous, when the script declares it),
 *   - the optional list of trusted proxies,
 *   - every check on a bearer token: signature, algorithm, issuer, audience, expiry and key id,
 *   - the key set: fetched through the OpenID configuration, cached, refreshed once for an unknown key id.
 *
 * Tokens are signed with an RSA key that the test makes itself; the identity service is a closure
 * that serves its OpenID configuration and key set. No database, no network, no Docker.
 *
 * Run:
 *     php test/unit/DoorTest.php
 *
 * Exits 0 when all checks pass, 1 otherwise.
 */

require_once __DIR__ . '/../../backend/lib/autoload.php';

use Ampersand\Exception\InvalidTokenException;
use Ampersand\Misc\Door;
use Ampersand\Misc\DoorKeySet;
use Ampersand\Misc\Settings;
use Firebase\JWT\JWT;
use Psr\Log\NullLogger;

const ISSUER = 'https://id.example.org';
const AUDIENCE = 'https://app.example.org';
const DECLARED = ['Administrator', 'Editor', 'Reader', 'Stampreporter', 'ExecEngine', 'SYSTEM', 'Any', 'Anonymous'];

$pass = 0;
$fail = 0;
function check(string $label, $actual, $expected): void
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}\n       expected " . var_export($expected, true) . "\n       got      " . var_export($actual, true) . "\n";
    }
}

function settings(array $values = []): Settings
{
    $settings = new Settings(new NullLogger());
    $settings->set(Door::SETTING_MODE, Door::MODE_PROXY);
    $settings->set(Door::SETTING_TOKEN_ISSUER, ISSUER);
    $settings->set(Door::SETTING_TOKEN_AUDIENCE, AUDIENCE);
    foreach ($values as $key => $value) {
        $settings->set($key, $value);
    }
    return $settings;
}

/** An RSA key pair, with its public half as a JSON Web Key. */
function keyPair(string $kid): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privatePem);
    $details = openssl_pkey_get_details($key);
    $jwk = [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $kid,
        'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
        'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
    ];
    return ['private' => $privatePem, 'public' => $details['key'], 'jwk' => $jwk, 'kid' => $kid];
}

/** An identity service that serves its configuration and the given keys, and counts the key fetches. */
class FakeIdentityService
{
    public int $keyFetches = 0;
    public function __construct(public array $jwks)
    {
    }
    public function fetch(string $url): string
    {
        if ($url === ISSUER . '/.well-known/openid-configuration') {
            return json_encode(['issuer' => ISSUER, 'jwks_uri' => ISSUER . '/.well-known/jwks.json']);
        }
        if ($url === ISSUER . '/.well-known/jwks.json') {
            $this->keyFetches++;
            return json_encode(['keys' => $this->jwks]);
        }
        throw new RuntimeException("unexpected URL {$url}");
    }
}

function keySet(FakeIdentityService $service): DoorKeySet
{
    $cacheFile = tempnam(sys_get_temp_dir(), 'door-test-');
    unlink($cacheFile);
    return new DoorKeySet(ISSUER, $cacheFile, Closure::fromCallable([$service, 'fetch']));
}

function token(array $keyPair, array $claims = [], string $alg = 'RS256'): string
{
    $claims = array_merge([
        'iss' => ISSUER,
        'aud' => AUDIENCE,
        'exp' => time() + 3600,
        'iat' => time(),
        'scp' => ['Stampreporter'],
        'scope' => 'Stampreporter',
    ], $claims);
    $claims = array_filter($claims, fn ($value) => $value !== null);
    return JWT::encode($claims, $keyPair['private'], $alg, $keyPair['kid']);
}

/** The roles of a request, or the message of the exception it raised. */
function roles(Settings $settings, array $server, ?DoorKeySet $keySet = null, array $declared = DECLARED): array|string
{
    try {
        return Door::rolesFromRequest($settings, $server, $declared, $keySet, new NullLogger());
    } catch (InvalidTokenException $e) {
        return 'refused: ' . $e->getMessage();
    }
}

$key = keyPair('key-1');
$service = new FakeIdentityService([$key['jwk']]);
$keys = keySet($service);

echo "The door is a setting\n";
$off = new Settings(new NullLogger());
check("the door is off by default", Door::isEnabled($off), false);
check("session.door: proxy switches it on", Door::isEnabled(settings()), true);

echo "Roles from the groups header\n";
check("names in one header line become roles",
    roles(settings(), ['HTTP_X_AUTH_REQUEST_GROUPS' => 'Editor,Reader']), ['Editor', 'Reader']);
check("repeated header lines, as Apache joins them, become roles",
    roles(settings(), ['HTTP_X_AUTH_REQUEST_GROUPS' => 'Editor, Reader']), ['Editor', 'Reader']);
check("a name the script does not declare is ignored",
    roles(settings(), ['HTTP_X_AUTH_REQUEST_GROUPS' => 'Editor, Hacker']), ['Editor']);
check("the framework's own roles and the wildcards are never taken from the header",
    roles(settings(), ['HTTP_X_AUTH_REQUEST_GROUPS' => 'ExecEngine,SYSTEM,Any,Anonymous']), []);
check("a name is compared exactly, case included",
    roles(settings(), ['HTTP_X_AUTH_REQUEST_GROUPS' => 'editor']), []);
check("an empty header gives no roles",
    roles(settings(), ['HTTP_X_AUTH_REQUEST_GROUPS' => '']), []);
check("another header name can be configured",
    roles(settings([Door::SETTING_GROUPS_HEADER => 'Remote-Groups']), ['HTTP_REMOTE_GROUPS' => 'Reader']), ['Reader']);

echo "A request that presents nothing\n";
check("gets Anonymous when the script declares it", roles(settings(), []), ['Anonymous']);
check("gets no role when the script does not", roles(settings(), [], null, ['Editor']), []);
check("an empty Authorization header presents nothing", roles(settings(), ['HTTP_AUTHORIZATION' => '']), ['Anonymous']);

echo "Trusted proxies\n";
$trusted = settings([Door::SETTING_TRUSTED_PROXIES => '172.18.0.0/16, 10.0.0.5']);
check("the header counts from an address in a trusted range",
    roles($trusted, ['REMOTE_ADDR' => '172.18.4.2', 'HTTP_X_AUTH_REQUEST_GROUPS' => 'Editor']), ['Editor']);
check("the header counts from a trusted single address",
    roles($trusted, ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_AUTH_REQUEST_GROUPS' => 'Editor']), ['Editor']);
check("the header is ignored from any other address",
    roles($trusted, ['REMOTE_ADDR' => '172.19.0.1', 'HTTP_X_AUTH_REQUEST_GROUPS' => 'Editor']), ['Anonymous']);
check("an IPv6 address lies in its range", Door::addressInRange('2001:db8::17', '2001:db8::/32'), true);
check("an IPv6 address outside its range", Door::addressInRange('2001:db9::17', '2001:db8::/32'), false);
check("an IPv4 address never lies in an IPv6 range", Door::addressInRange('10.0.0.5', '::/0'), false);
check("a prefix that is not a byte boundary", Door::addressInRange('192.168.1.130', '192.168.1.128/25'), true);
check("just outside that prefix", Door::addressInRange('192.168.1.127', '192.168.1.128/25'), false);

echo "The bearer token in the Authorization header\n";
check("Bearer is read", Door::bearerToken(['HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi']), 'abc.def.ghi');
check("bearer in lower case is read", Door::bearerToken(['HTTP_AUTHORIZATION' => 'bearer abc.def.ghi']), 'abc.def.ghi');
check("after a rewrite Apache names it REDIRECT_HTTP_AUTHORIZATION",
    Door::bearerToken(['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi']), 'abc.def.ghi');
check("Basic is no bearer token", Door::bearerToken(['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwdw==']), null);

echo "Roles from a valid token\n";
check("the permissions in scp become roles",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key)], $keys), ['Stampreporter']);
check("the framework's own roles are never taken from a token",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key, ['scp' => ['Reader', 'ExecEngine', 'SYSTEM']])], $keys), ['Reader']);
check("without scp, the space-separated scope counts",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key, ['scp' => null, 'scope' => 'Reader Editor'])], $keys), ['Reader', 'Editor']);
check("an audience in a list counts",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key, ['aud' => ['other', AUDIENCE . '/']])], $keys), ['Stampreporter']);
check("a token wins over a groups header in the same request",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key), 'HTTP_X_AUTH_REQUEST_GROUPS' => 'Administrator'], $keys), ['Stampreporter']);

echo "Tokens that are refused\n";
$stranger = keyPair('key-1');
check("a token signed with another key",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($stranger)], $keys),
    'refused: The bearer token was not accepted: its signature does not match');
check("a token from another issuer",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key, ['iss' => 'https://evil.example.org'])], $keys),
    'refused: The bearer token was not accepted: it comes from another issuer');
check("a token for another application",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key, ['aud' => 'https://other.example.org'])], $keys),
    'refused: The bearer token was not accepted: it is meant for another application');
check("an expired token",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key, ['exp' => time() - 120])], $keys),
    'refused: The bearer token was not accepted: it has expired');
check("a token without expiry",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key, ['exp' => null])], $keys),
    'refused: The bearer token was not accepted: it has no expiry');
$hmac = JWT::encode(['iss' => ISSUER, 'aud' => AUDIENCE, 'exp' => time() + 60, 'scp' => ['Administrator']], $key['public'], 'HS256', 'key-1');
check("a token signed with HS256 and the public key as secret",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . $hmac], $keys),
    'refused: The bearer token was not accepted: its algorithm is not allowed');
$parts = explode('.', token($key));
$none = JWT::urlsafeB64Encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])) . '.' . $parts[1] . '.';
check("a token with algorithm none",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . $none], $keys),
    'refused: The bearer token was not accepted: its algorithm is not allowed');
check("something that is not a JWT",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer not-a-token'], $keys),
    'refused: The bearer token was not accepted: it is not a JWT');
check("a token while no issuer is configured",
    roles(settings([Door::SETTING_TOKEN_ISSUER => null]), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key)], null),
    'refused: The application cannot check bearer tokens: no token issuer is configured');

echo "The key set\n";
$service2 = new FakeIdentityService([$key['jwk']]);
$keys2 = keySet($service2);
roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key)], $keys2);
roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key)], $keys2);
check("two requests fetch the keys once", $service2->keyFetches, 1);

$rotated = keyPair('key-2');
$service2->jwks[] = $rotated['jwk'];
check("a token with a key id that the cache does not know is refused while the cache is young",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($rotated)], $keys2),
    'refused: The bearer token was not accepted: it cannot be verified');
check("and the keys were not fetched again within a minute", $service2->keyFetches, 1);

// Make the cache older than a minute: the unknown key id now fetches the keys once more.
$cacheFile = (new ReflectionProperty(DoorKeySet::class, 'cacheFile'))->getValue($keys2);
$cached = json_decode(file_get_contents($cacheFile), true);
$cached['fetchedAt'] = time() - DoorKeySet::MIN_REFRESH_SECONDS - 5;
file_put_contents($cacheFile, json_encode($cached));
check("after a minute, a rotated key is fetched and accepted",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($rotated)], $keys2), ['Stampreporter']);
check("and the keys were fetched a second time", $service2->keyFetches, 2);

$unreachable = new DoorKeySet(ISSUER, sys_get_temp_dir() . '/door-test-none-' . bin2hex(random_bytes(4)),
    function (string $url): string { throw new RuntimeException('connection refused'); });
check("without reachable keys, a token is refused",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key)], $unreachable),
    'refused: The bearer token cannot be checked: the keys of the identity service are not available');

$liar = new FakeIdentityService([$key['jwk']]);
$liarKeys = new DoorKeySet(ISSUER, sys_get_temp_dir() . '/door-test-liar-' . bin2hex(random_bytes(4)),
    function (string $url) use ($liar): string {
        return str_ends_with($url, 'openid-configuration')
            ? json_encode(['issuer' => 'https://evil.example.org', 'jwks_uri' => ISSUER . '/.well-known/jwks.json'])
            : $liar->fetch($url);
    });
check("a configuration that names another issuer yields no keys",
    roles(settings(), ['HTTP_AUTHORIZATION' => 'Bearer ' . token($key)], $liarKeys),
    'refused: The bearer token cannot be checked: the keys of the identity service are not available');

echo "\n{$pass} checks passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
