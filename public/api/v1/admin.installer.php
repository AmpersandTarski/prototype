<?php

use Ampersand\Log\Logger;
use Ampersand\Misc\Installer;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @var \Slim\App $api
 */
global $api;

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/admin/installer', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Additional access check. Reinstalling the whole application is not allowed in production environment
        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Reinstallation of application not allowed in production environment", 403);
        }
        
        $defaultPop = filter_var($request->getQueryParam('defaultPop'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $ignoreInvariantRules = filter_var($request->getQueryParam('ignoreInvariantRules'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        $ampersandApp
            ->reinstall($defaultPop, $ignoreInvariantRules) // reinstall and initialize application
            ->setSession();
    })->setName('applicationInstaller');

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/metapopulation', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $transaction = $ampersandApp->newTransaction();

        $installer = new Installer(Logger::getLogger('APPLICATION'));
        $installer->reinstallMetaPopulation($ampersandApp->getModel());
        
        $transaction->runExecEngine()->close(false, false);
        if ($transaction->isRolledBack()) {
            throw new Exception("Meta population does not satisfy invariant rules. See log files", 500);
        }
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/navmenu', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $transaction = $ampersandApp->newTransaction();

        $installer = new Installer(Logger::getLogger('APPLICATION'));
        $installer->reinstallNavigationMenus($ampersandApp->getModel());

        $transaction->runExecEngine()->close(false, false);
        if ($transaction->isRolledBack()) {
            throw new Exception("Nav menu population does not satisfy invariant rules. See log files", 500);
        }
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/checksum/update', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $ampersandApp
            ->registerCurrentModelVersion()
            ->init()
            ->setSession();
        
        $ampersandApp->userLog()->info('New model version registered. Checksum is updated');
    })->setName('updateChecksum');
})->add($middleWare1)
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $req, Response $res, callable $next) {
    /** @var \Ampersand\AmpersandApp $ampersandApp */
    $ampersandApp = $this['ampersand_app'];

    /** @var \Slim\Route $route */
    $route = $req->getAttribute('route');

    // Access check (except for route 'applicationInstaller', because session may not be set yet)
    if ($route->getName() !== 'applicationInstaller') {
        $allowedRoles = $ampersandApp->getSettings()->get('rbac.adminRoles');
        if (!$ampersandApp->hasRole($allowedRoles)) {
            throw new Exception("You do not have access to /admin/installer", 403);
        }
    }

    // Do stuff
    $response = $next($req, $res);

    // Create response message
    $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
    $content = $ampersandApp->userLog()->getAll(); // Return all notifications

    return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});
