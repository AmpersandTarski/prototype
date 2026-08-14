<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Controller;

use Ampersand\Exception\BadRequestException;
use Ampersand\Rule\RuleEngine;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Controls the import-bootstrap mode (see AmpersandApp::isImportMode() and the
 * "Importing Large Datasets" guide). While a prototype is in import mode it boots locked
 * into the import screen; imports commit without checking invariants; this controller's
 * "Start checking" runs the one-time full check that either unlocks the app (green) or
 * keeps it locked (red).
 */
class ImportModeController extends AbstractController
{
    /**
     * "Start checking": evaluate every invariant once over the whole imported population.
     * If none is violated the prototype is unlocked for normal use (permanent); otherwise it
     * stays locked and the violations are returned. Only meaningful while in import mode.
     */
    public function startChecking(Request $request, Response $response, array $args): Response
    {
        if (!$this->app->isImportMode()) {
            throw new BadRequestException("The application is not in import mode.");
        }

        // Evaluate all conjuncts once (this refreshes their cache), then collect the
        // invariant violations. Signal (process) rules do not gate the application start.
        foreach ($this->app->getModel()->getAllConjuncts() as $conj) {
            $conj->evaluate()->persistCacheItem();
        }

        $violated = false;
        foreach (RuleEngine::getViolations($this->app->getModel()->getAllRules('invariant')) as $violation) {
            $violated = true;
            $this->app->userLog()->invariant($violation);
        }

        if ($violated) {
            $this->app->userLog()->warning("The application cannot start while invariant rules are violated. Import more data to resolve them, then check again.");
        } else {
            $this->app->unlockImportMode(); // green: unlock the prototype permanently
            $this->app->userLog()->notice("All invariants hold. The application is unlocked.");
        }

        return $response->withJson(
            [ 'locked'        => $violated
            , 'notifications' => $this->app->userLog()->getAll()
            ],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
