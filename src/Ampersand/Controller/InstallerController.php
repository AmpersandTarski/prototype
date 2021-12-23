<?php

namespace Ampersand\Controller;

use Ampersand\Exception\AccessDeniedException;
use Exception;
use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Misc\Installer;
use Ampersand\Log\Logger;

class InstallerController extends AbstractController
{
    protected function guard(): void
    {
        $allowedRoles = $this->app->getSettings()->get('rbac.adminRoles');
        if (!$this->app->hasRole($allowedRoles)) {
            throw new AccessDeniedException("You do not have access to /admin/installer", 403);
        }
    }

    public function install(Request $request, Response $response, array $args): Response
    {
        // $this->guard(); // skip generic access control check

        // Access control check. Reinstalling the whole application is not allowed in production environment
        if ($this->app->getSettings()->get('global.productionEnv')) {
            throw new AccessDeniedException("Reinstallation of application not allowed in production environment", 403);
        }
        
        $defaultPop = filter_var($request->getQueryParam('defaultPop'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $ignoreInvariantRules = filter_var($request->getQueryParam('ignoreInvariantRules'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        $this->app
            ->reinstall($defaultPop, $ignoreInvariantRules) // reinstall and initialize application
            ->setSession();
        
        return $this->success($response);
    }

    public function installMetaPopulation(Request $request, Response $response, array $args): Response
    {
        $this->guard();

        $transaction = $this->app->newTransaction();

        $installer = new Installer(Logger::getLogger('APPLICATION'));
        $installer->reinstallMetaPopulation($this->app->getModel());
        
        $transaction->runExecEngine()->close(false, false);
        if ($transaction->isRolledBack()) {
            throw new Exception("Meta population does not satisfy invariant rules. See log files", 500);
        }

        return $this->success($response);
    }

    public function installNavmenu(Request $request, Response $response, array $args): Response
    {
        $this->guard();

        $transaction = $this->app->newTransaction();

        $installer = new Installer(Logger::getLogger('APPLICATION'));
        $installer->reinstallNavigationMenus($this->app->getModel());

        $transaction->runExecEngine()->close(false, false);
        if ($transaction->isRolledBack()) {
            throw new Exception("Nav menu population does not satisfy invariant rules. See log files", 500);
        }

        return $this->success($response);
    }

    public function updateChecksum(Request $request, Response $response, array $args): Response
    {
        $this->guard();
        
        $this->app
            ->registerCurrentModelVersion()
            ->init()
            ->setSession();
        
        $this->app->userLog()->info('New model version registered. Checksum is updated');

        return $this->success($response);
    }
}
