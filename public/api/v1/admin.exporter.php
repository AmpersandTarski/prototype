<?php

use Exception;
use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\IO\Exporter;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Ampersand\Log\Logger;
use Ampersand\IO\RDFGraph;

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
    
    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/export/all', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Export not allowed in production environment", 403);
        }
        
        // Export population to response body
        $exporter = new Exporter(new JsonEncoder(), $response->getBody(), Logger::getLogger('IO'));
        $exporter->exportAllPopulation('json');

        // Return response
        $filename = $ampersandApp->getName() . "_population_" . date('Y-m-d\TH-i-s') . ".json";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/export/metamodel', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Export of meta model not allowed in production environment", 403);
        }

        // Content negotiation
        $acceptHeader = $request->getParam('format') ?? $request->getHeaderLine('Accept');
        $easyRdf_Format = RDFGraph::getResponseFormat($acceptHeader);

        $graph = new RDFGraph($ampersandApp);

        // Output
        switch ($mimetype = $easyRdf_Format->getDefaultMimeType()) {
            case 'text/html':
                return $response->withHeader('Content-Type', 'text/html')->write($graph->dump('html'));
            case 'text/plain':
                return $response->withHeader('Content-Type', 'text/plain')->write($graph->dump('text'));
            default:
                return $response
                    ->withHeader('Content-Type', $easyRdf_Format->getDefaultMimeType())
                    ->withHeader('Content-Disposition', "attachment; filename=\"app-meta-model.{$easyRdf_Format->getDefaultExtension()}\"")
                    ->write($graph->serialise($easyRdf_Format));
        }
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
        throw new Exception("You do not have access to /admin/exporter", 403);
    }

    // Do stuff
    $response = $next($req, $res);

    // Create response message
    $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
    $content = $ampersandApp->userLog()->getAll(); // Return all notifications

    return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});
