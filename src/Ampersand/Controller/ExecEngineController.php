<?php

namespace Ampersand\Controller;

use Exception;
use Slim\Http\Request;
use Slim\Http\Response;

class ExecEngineController extends AbstractController
{
    public function run(Request $request, Response $response, array $args): Response
    {
        // Check for required role
        $allowedRoles = $this->app->getSettings()->get('rbac.adminRoles');
        if (!$this->app->hasRole($allowedRoles)) {
            throw new Exception("You do not have access to run the exec engine", 403);
        }
        
        $transaction = $this->app->newTransaction()->runExecEngine(true)->close();

        if ($transaction->isCommitted()) {
            $this->app->userLog()->notice("Run completed");
        } else {
            $this->app->userLog()->warning("Run completed but transaction not committed");
        }

        $this->app->checkProcessRules(); // Check all process rules that are relevant for the activate roles
        
        return $response->withJson(
            $this->app->userLog()->getAll(),
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
