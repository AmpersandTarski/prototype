<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Psr\Log\LoggerInterface;

/**
 * Service key: lets a machine reach the administrative endpoints while the application runs
 * in production mode.
 *
 * Production mode (`global.productionEnv`) closes the installer, the population exporter, the
 * reports and the test-login endpoint for everyone. A deployment pipeline needs exactly those
 * endpoints: it calls the exporter and the installer to migrate the population when the model
 * changes. Without a way in, an administrator has to choose between a working pipeline and a
 * protected application.
 *
 * A request that carries the configured key in the `X-Ampersand-Service-Key` header passes the
 * production-mode gate. Every other request gets what it got before.
 *
 * The mechanism fails closed. When no key is configured — the default — production mode blocks
 * every request, exactly as it did before this feature existed. A key that is empty or consists
 * of whitespace only counts as not configured.
 *
 * The key is never written to a log line, an error message or a response. `Settings::set()` masks
 * it, the refusal message is the same for every reason, and the comparison keeps the key out of
 * function arguments so that it cannot surface in a stack trace either.
 *
 * @see \Ampersand\Controller\AbstractController::preventProductionMode()
 */
final class ServiceKey
{
    /**
     * Setting that holds the configured key. Set it with environment variable AMPERSAND_SERVICE_KEY.
     */
    public const SETTING = 'global.serviceKey';

    /**
     * HTTP request header that carries the key.
     *
     * A header keeps the key out of the places a URL is written down: the access log of the web
     * server, the `url` field that the framework's own WebProcessor adds to every log record, the
     * browser history and the referrer of a next request. A query parameter would end up in all of
     * them. The name is application specific on purpose: `Authorization` is reserved for the
     * session of a user and is stripped by some Apache configurations before PHP sees it.
     */
    public const HEADER = 'X-Ampersand-Service-Key';

    /**
     * Server parameter ($_SERVER) under which PHP exposes the header above.
     */
    public const SERVER_PARAM = 'HTTP_X_AMPERSAND_SERVICE_KEY';

    /**
     * Tells whether the production-mode gate refuses this request.
     *
     * Returns false (the request may pass) in two cases: the application is not in production
     * mode, or the request carries the configured service key. In every other case the answer is
     * true and the caller refuses the request.
     *
     * Both the settings and the server parameters arrive as an object and an array, so a stack
     * trace of an exception thrown from here shows "Object(Ampersand\Misc\Settings)" and "Array"
     * instead of the key itself.
     */
    public static function productionGuardApplies(Settings $settings, array $serverParams, ?LoggerInterface $logger = null): bool
    {
        // Outside production mode there is nothing to guard.
        if (!$settings->get('global.productionEnv', false)) {
            return false;
        }

        // No key configured (the default), or a key of whitespace only: fail closed.
        $configuredKey = $settings->get(self::SETTING, '');
        if (!is_string($configuredKey) || trim($configuredKey) === '') {
            return true;
        }

        // No key presented, or a key of whitespace only.
        $presentedKey = $serverParams[self::SERVER_PARAM] ?? '';
        if (!is_string($presentedKey) || trim($presentedKey) === '') {
            return true;
        }

        // Compare the digests, not the keys themselves: hash_equals takes constant time for
        // strings of equal length, and equal-length digests keep the length of the configured
        // key out of the timing as well.
        if (!hash_equals(hash('sha256', trim($configuredKey)), hash('sha256', trim($presentedKey)))) {
            return true;
        }

        // Record that a machine used the key, without recording the key.
        $logger?->notice("Service key accepted: administrative endpoint used in production mode");

        return false;
    }
}
