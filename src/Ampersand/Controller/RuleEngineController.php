<?php

namespace Ampersand\Controller;

use Ampersand\Rule\RuleEngine;
use Exception;
use Slim\Http\Request;
use Slim\Http\Response;

class RuleEngineController extends AbstractController
{
    public function evaluateAllRules(Request $request, Response $response, array $args): Response
    {
        // Check for required role
        $allowedRoles = $this->app->getSettings()->get('rbac.adminRoles');
        if (!$this->app->hasRole($allowedRoles)) {
            throw new Exception("You do not have access to evaluate all rules", 403);
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
