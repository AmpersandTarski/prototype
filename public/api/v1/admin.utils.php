<?php

use Ampersand\Core\Concept;
use Ampersand\Misc\ProtoContext;
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
    $this->get('/regenerate-all-atom-ids[/{concept}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Input
        $cptName = isset($args['concept']) ? $args['concept'] : null;
        $prefixWithConceptName = filter_var($request->getQueryParam('prefix'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // Determine which concepts to regenerate atom ids
        if (!is_null($cptName)) {
            $cpt = $ampersandApp->getModel()->getConcept($cptName);
            if (!$cpt->isObject()) {
                throw new Exception("Regenerate atom ids is not allowed for scalar concept types (alphanumeric, decimal, date, ..)", 400);
            }
            $conceptList = [$cpt];
        } else {
            $conceptList = array_filter($ampersandApp->getModel()->getAllConcepts(), function (Concept $concept) {
                return $concept->isObject() // we only regenerate object identifiers, not scalar concepts because that wouldn't make sense
                    && !ProtoContext::containsConcept($concept) // filter out concepts from ProtoContext, otherwise interfaces and RBAC doesn't work anymore
                    && $concept->isRoot(); // specializations are automatically handled, therefore we only take root concepts (top of classification tree)
            });
        }

        // Do the work
        $transaction = $ampersandApp->newTransaction();
        
        foreach ($conceptList as $concept) {
            $concept->regenerateAllAtomIds($prefixWithConceptName);
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
