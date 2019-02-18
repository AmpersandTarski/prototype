<?php

use Ampersand\Log\Logger;
use Ampersand\Misc\Installer;
use Exception;
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

        $installer = new Installer($ampersandApp, Logger::getLogger('APPLICATION'));
        $installer->reinstallMetaPopulation();
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/navmenu', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $installer = new Installer($ampersandApp, Logger::getLogger('APPLICATION'));
        $installer->reinstallNavigationMenus();
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/checksum/update', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $ampersandApp->getModel()->writeChecksumFile();
        
        $ampersandApp->userLog()->info('New checksum calculated for generated Ampersand model files');
    });
})->add($middleWare1)
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $req, Response $res, callable $next) {
    /** @var \Ampersand\AmpersandApp $ampersandApp */
    $ampersandApp = $this['ampersand_app'];

    // Access check
    $allowedRoles = $ampersandApp->getSettings()->get('rbac.adminRoles');
    if (!$ampersandApp->hasRole($allowedRoles)) {
        throw new Exception("You do not have access to /admin/installer", 403);
    }

    // Do stuff
    $response = $next($req, $res);

    // Create response message
    $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
    $content = $ampersandApp->userLog()->getAll(); // Return all notifications

    return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});
