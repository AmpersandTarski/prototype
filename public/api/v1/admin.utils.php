<?php

use Ampersand\Core\Concept;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @var \Slim\App $api
 */
global $api;

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/admin/utils', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/regenerate-all-atom-ids', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $transaction = $ampersandApp->newTransaction();

        // Determine which concepts to regenerate atom ids
        $conceptList = array_filter($ampersandApp->getModel()->getAllConcepts(), function (Concept $concept) {
            return $concept->isObject() // we only regenerate object identifiers, not scalar concepts because that wouldn't make sense
                && $concept->isRoot(); // specializations are automatically handled, therefore we only take root concepts (top of classification tree)
        });

        // Do the work
        foreach ($conceptList as $concept) {
            $concept->regenerateAllAtomIds();
        }
        
        // Close transaction
        $transaction->runExecEngine()->close();
        $transaction->isCommitted() ? $ampersandApp->userLog()->notice("Run completed") : $ampersandApp->userLog()->warning("Run completed but transaction not committed");
        
        // Return
        return $response->withJson(
            $ampersandApp->userLog()->getAll(),
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    });
})
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $req, Response $res, callable $next) {
    /** @var \Ampersand\AmpersandApp $ampersandApp */
    $ampersandApp = $this['ampersand_app'];

    // Access check
    $allowedRoles = $ampersandApp->getSettings()->get('rbac.adminRoles');
    if (!$ampersandApp->hasRole($allowedRoles)) {
        throw new Exception("You do not have access to /admin/utils", 403);
    }

    // Do stuff
    return $next($req, $res);
});
