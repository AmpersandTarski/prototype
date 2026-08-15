<?php

namespace Ampersand\Controller;

use Ampersand\Exception\AccessDeniedException;
use Ampersand\Rule\RuleEngine;
use Slim\Http\Request;
use Slim\Http\Response;

class RuleEngineController extends AbstractController
{
    /**
     * Return all signal violations for the currently active roles.
     * Reads directly from the conjunct violation cache (the database table __conj_violation_cache__).
     * No role required beyond being logged in — only signals for the active roles are returned.
     */
    public function getSignalViolations(Request $request, Response $response, array $args): Response
    {
        // Clear any previously accumulated notifications so we get a clean result
        $this->app->userLog()->clearAll();

        // Re-evaluate signals from the conjunct cache for the currently active roles
        $this->app->checkProcessRules();

        // Return only the signals part of the notification log
        $all = $this->app->userLog()->getAll();
        return $response->withJson(
            $all['signals'],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function evaluateAllRules(Request $request, Response $response, array $args): Response
    {
        // Check for required role
        $allowedRoles = $this->app->getSettings()->get('rbac.adminRoles');
        if (!$this->app->hasRole($allowedRoles)) {
            throw new AccessDeniedException("You do not have access to evaluate all rules");
        }

        foreach ($this->app->getModel()->getAllConjuncts() as $conj) {
            /** @var \Ampersand\Rule\Conjunct $conj */
            $conj->evaluate()->persistCacheItem();
        }
        
        foreach (RuleEngine::getViolations($this->app->getModel()->getAllRules('invariant')) as $violation) {
            $this->app->userLog()->invariant($violation);
        }
        foreach (RuleEngine::getViolations($this->app->getModel()->getAllRules('signal')) as $violation) {
            $this->app->userLog()->signal($violation);
        }
        
        return $response->withJson(
            $this->app->userLog()->getAll(),
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
