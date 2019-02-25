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

        // Export population to response body
        $exporter = new Exporter(new JsonEncoder(), $response->getBody(), Logger::getLogger('IO'));
        $exporter->exportPopulation($ampersandApp->getModel()->getAllConcepts(), $ampersandApp->getModel()->getRelations(), 'json');

        // Return response
        $filename = $ampersandApp->getName() . "_population_" . date('Y-m-d\TH-i-s') . ".json";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->post('/export/selection', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Process input
        $body = $request->reparseBody()->getParsedBody();
        $model = $ampersandApp->getModel();
        
        $concepts = array_map(function (string $conceptLabel) use ($model) {
            return $model->getConceptByLabel($conceptLabel);
        }, $body->concepts);

        $relations = array_map(function (string $relSignature) use ($model) {
            return $model->getRelation($relSignature);
        }, $body->relations);

        // Export population to response body
        $exporter = new Exporter(new JsonEncoder(), $response->getBody(), Logger::getLogger('IO'));
        $exporter->exportPopulation($concepts, $relations, 'json');

        // Return response
        $filename = $ampersandApp->getName() . "_population-subset_" . date('Y-m-d\TH-i-s') . ".json";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/export/metamodel', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

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
                $filename = $ampersandApp->getName() . "_meta-model_" . date('Y-m-d\TH-i-s') . "." . $easyRdf_Format->getDefaultExtension();
                return $response
                    ->withHeader('Content-Type', $easyRdf_Format->getDefaultMimeType())
                    ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
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

    if ($ampersandApp->getSettings()->get('global.productionEnv')) {
        throw new Exception("Export not allowed in production environment", 403);
    }

    // Do stuff
    return $next($req, $res);
});
