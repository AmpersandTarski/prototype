<?php

/**
 * Standalone test for the service key that lifts the production-mode gate
 * ({@see \Ampersand\Misc\ServiceKey} and {@see \Ampersand\Controller\AbstractController::preventProductionMode()}).
 *
 * The four cases that matter are driven through the real guard of the real controller, with the
 * key in $_SERVER exactly as PHP delivers the X-Ampersand-Service-Key header:
 *
 *   1. no production mode, no key      -> the request passes, as it always did
 *   2. production mode, no key         -> refused
 *   3. production mode, correct key    -> the request passes
 *   4. production mode, wrong key      -> refused
 *
 * The rest of the checks cover the ways in which the mechanism must fail closed (an empty,
 * whitespace-only, unset or non-string key) and the promise that the key never reaches a log
 * line, an error message or a stack trace.
 *
 * Runs on the host with plain PHP — no database, no Ampersand compiler, no Docker. It builds a
 * Settings object on the framework defaults and a controller without a DI-container.
 *
 * Run:
 *     php test/unit/ServiceKeyProductionGuardTest.php
 *
 * Exits 0 when all checks pass, 1 otherwise.
 */

require_once __DIR__ . '/../../backend/lib/autoload.php';

use Ampersand\AmpersandApp;
use Ampersand\Controller\AbstractController;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Misc\ServiceKey;
use Ampersand\Misc\Settings;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Registry;
use Psr\Log\NullLogger;

const CONFIGURED_KEY = 's3cret-deployment-key-9f2a';

/** Register the log channel that the controller writes its audit line to, and keep the records. */
$logHandler = new TestHandler();
Registry::addLogger(new MonologLogger('APPLICATION', [$logHandler]));

/** Controller that exposes the guard under test; the DI-container is not needed for it. */
class GuardedController extends AbstractController
{
    public function __construct(private AmpersandApp $ampersandApp)
    {
        $this->app = $ampersandApp;
    }

    public function callGuard(): void
    {
        $this->preventProductionMode();
    }
}

/** Settings on the framework defaults, with the two settings of this feature set explicitly. */
function makeSettings(bool $productionEnv, mixed $serviceKey, \Psr\Log\LoggerInterface $logger = new NullLogger()): Settings
{
    $settings = new Settings($logger);
    $settings->set('global.productionEnv', $productionEnv);
    $settings->set(ServiceKey::SETTING, $serviceKey);
    return $settings;
}

/** Controller on those settings, without booting the application. */
function makeController(Settings $settings): GuardedController
{
    $app = (new ReflectionClass(AmpersandApp::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty(AmpersandApp::class, 'settings');
    $property->setValue($app, $settings);
    return new GuardedController($app);
}

/**
 * Run the guard of the controller with the given header value ($presentedKey; null = no header)
 * and report whether the request was refused.
 */
function requestIsRefused(Settings $settings, mixed $presentedKey): bool
{
    global $lastException;
    $lastException = null;

    unset($_SERVER[ServiceKey::SERVER_PARAM]);
    if (!is_null($presentedKey)) {
        $_SERVER[ServiceKey::SERVER_PARAM] = $presentedKey;
    }

    try {
        makeController($settings)->callGuard();
        return false;
    } catch (AccessDeniedException $e) {
        $lastException = $e;
        return true;
    } finally {
        unset($_SERVER[ServiceKey::SERVER_PARAM]);
    }
}

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
        echo "  FAIL {$label}\n       expected: " . var_export($expected, true) . "\n       actual:   " . var_export($actual, true) . "\n";
    }
}

echo "The four cases, through the guard of the controller\n";

// 1. Not in production: everything works as it did before this feature existed. A key is neither
//    configured nor needed, and a request that happens to carry one is not treated differently.
$devWithoutKey = makeSettings(false, null);
check("1. no production mode, no key: request passes", requestIsRefused($devWithoutKey, null), false);
check("1. no production mode, key presented anyway: request passes", requestIsRefused($devWithoutKey, CONFIGURED_KEY), false);

// 2. Production mode without a configured key: the gate is closed, for everyone.
$prodWithoutKey = makeSettings(true, null);
check("2. production mode, no key configured, no key presented: refused", requestIsRefused($prodWithoutKey, null), true);
check("2. production mode, no key configured, key presented: refused", requestIsRefused($prodWithoutKey, CONFIGURED_KEY), true);

// 3. Production mode with the configured key: the machine passes.
$prodWithKey = makeSettings(true, CONFIGURED_KEY);
check("3. production mode, correct key: request passes", requestIsRefused($prodWithKey, CONFIGURED_KEY), false);
check("3. production mode, correct key with surrounding whitespace: request passes", requestIsRefused($prodWithKey, " " . CONFIGURED_KEY . " "), false);

// 4. Production mode with anything but the configured key: refused.
check("4. production mode, wrong key: refused", requestIsRefused($prodWithKey, 'wrong-key'), true);
check("4. production mode, key with one character changed: refused", requestIsRefused($prodWithKey, substr(CONFIGURED_KEY, 0, -1) . 'b'), true);
check("4. production mode, key that is a prefix of the right one: refused", requestIsRefused($prodWithKey, substr(CONFIGURED_KEY, 0, 10)), true);
check("4. production mode, no key presented: refused", requestIsRefused($prodWithKey, null), true);

echo "\nFailing closed\n";

// An empty or whitespace-only configured key counts as no key at all: nothing gets in, not even a
// request that presents exactly that value.
check("empty key configured, empty key presented: refused", requestIsRefused(makeSettings(true, ''), ''), true);
check("whitespace key configured, same presented: refused", requestIsRefused(makeSettings(true, '   '), '   '), true);
check("empty key configured, no key presented: refused", requestIsRefused(makeSettings(true, ''), null), true);
check("key configured, empty key presented: refused", requestIsRefused($prodWithKey, ''), true);
check("key configured, whitespace-only key presented: refused", requestIsRefused($prodWithKey, "  \t "), true);

// A setting that was never set at all (an older project.yaml, a missing environment variable)
// must not throw and must not open the gate.
$prodSettingUnset = new Settings(new NullLogger());
$prodSettingUnset->set('global.productionEnv', true);
check("setting never set, no key presented: refused", requestIsRefused($prodSettingUnset, null), true);
check("setting never set, key presented: refused", requestIsRefused($prodSettingUnset, CONFIGURED_KEY), true);

// A non-string value on either side is refused rather than compared.
check("non-string key configured: refused", requestIsRefused(makeSettings(true, 42), '42'), true);
check("non-string key presented: refused", requestIsRefused($prodWithKey, ['a' => CONFIGURED_KEY]), true);

echo "\nThe key stays out of messages, logs and traces\n";

// The refusal names no reason, so a caller cannot tell a wrong key from an unconfigured one.
global $lastException;
requestIsRefused($prodWithKey, 'wrong-key');
$refusalOnWrongKey = $lastException?->getMessage() ?? '(no refusal)';
requestIsRefused($prodWithoutKey, null);
check("the refusal message is the same, with and without a configured key", $refusalOnWrongKey, $lastException?->getMessage() ?? '(no refusal)');
check("the refusal message is unchanged", $refusalOnWrongKey, "Not allowed in production environment");
check("the key is absent from the refusal message", str_contains($refusalOnWrongKey, CONFIGURED_KEY), false);

// The trace of the refusal shows the objects that carry the key, not the key.
requestIsRefused($prodWithKey, CONFIGURED_KEY . '-wrong');
$trace = $lastException?->getTraceAsString() ?? '(no refusal)';
check("the key is absent from the stack trace", str_contains($trace, CONFIGURED_KEY), false);
check("the presented key is absent from the stack trace", str_contains($trace, CONFIGURED_KEY . '-wrong'), false);

// The audit line records that the key was used, not which key.
$logHandler->clear();
requestIsRefused($prodWithKey, CONFIGURED_KEY);
$records = $logHandler->getRecords();
check("an accepted service key is recorded", count($records), 1);
check("the audit line names no key", str_contains((string) ($records[0]['message'] ?? ''), CONFIGURED_KEY), false);
check("the audit line is a notice", (string) ($records[0]['level_name'] ?? ''), 'NOTICE');

// A refused request is not announced as a service-key event at all.
$logHandler->clear();
requestIsRefused($prodWithKey, 'wrong-key');
check("a refused request writes no service-key line", count($logHandler->getRecords()), 0);

// Settings masks the key in its own debug log, which runs at DEBUG level on every deployment.
$settingsLogHandler = new TestHandler(MonologLogger::DEBUG);
$loggedSettings = makeSettings(true, CONFIGURED_KEY, new MonologLogger('SETTINGS', [$settingsLogHandler]));
$settingsLog = implode("\n", array_map(fn ($record) => (string) $record['message'], $settingsLogHandler->getRecords()));
check("the settings log mentions the setting", str_contains($settingsLog, 'global.servicekey'), true);
check("the settings log does not mention the key", str_contains($settingsLog, CONFIGURED_KEY), false);
check("the database password is masked in the same way", preg_match("/'mysql\.dbpass' to '\*\*\*'/", $settingsLog), 1);

echo "\n{$pass} checks passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
