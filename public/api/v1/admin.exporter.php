<?php

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
$api->group('/admin/exporter', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */
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
        throw new Exception("You do not have access to /admin/exporter", 403);
    }

    // Do stuff
    $response = $next($req, $res);

    // Create response message
    $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
    $content = $ampersandApp->userLog()->getAll(); // Return all notifications

    return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});
