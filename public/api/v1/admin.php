<?php

use Ampersand\Log\Logger;
use Ampersand\Rule\Conjunct;
use Ampersand\Rule\Rule;
use Ampersand\Rule\RuleEngine;
use Ampersand\IO\Exporter;
use Ampersand\IO\JSONWriter;
use Ampersand\IO\CSVWriter;
use Ampersand\IO\Importer;
use Ampersand\IO\JSONReader;
use Ampersand\IO\ExcelImporter;
use Ampersand\Misc\Reporter;
use Ampersand\Interfacing\Resource;
use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Session;
use Ampersand\Interfacing\ResourceFactory;

/**
 * @var \Slim\App $api
 */
global $api;

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/admin', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('/sessions/delete/expired', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Not allowed in production environment", 403);
        }
        
        $transaction = $ampersandApp->newTransaction();

        Session::deleteExpiredSessions();

        $transaction->runExecEngine()->close();
    });
    
    $this->post('/resource/{resourceType}/rename', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        
        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Not allowed in production environment", 403);
        }
        $resourceType = $args['resourceType'];
        
        $list = $request->reparseBody()->getParsedBody();
        if (!is_array($list)) {
            throw new Exception("Body must be array. Non-array provided", 500);
        }

        $transaction = $ampersandApp->newTransaction();

        foreach ($list as $item) {
            $resource = ResourceFactory::makeResource($item->oldId, $resourceType);
            $resource->rename($item->newId);
        }
        
        $transaction->runExecEngine()->close();

        return $response->withJson($list, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/installer', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Reinstallation of application not allowed in production environment", 403);
        }
        
        $defaultPop = filter_var($request->getQueryParam('defaultPop'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $ignoreInvariantRules = filter_var($request->getQueryParam('ignoreInvariantRules'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        $ampersandApp
            ->reinstall($defaultPop, $ignoreInvariantRules) // reinstall and initialize application
            ->setSession();

        $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles

        $content = $ampersandApp->userLog()->getAll(); // Return all notifications

        return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    })->setName('applicationInstaller');

    $this->get('/installer/checksum/update', function (Request $request, Response $response, $args = []) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Checksum update is not allowed in production environment", 403);
        }

        $ampersandApp->getModel()->writeChecksumFile();
        
        $ampersandApp->userLog()->info('New checksum calculated for generated Ampersand model files');

        $content = $ampersandApp->userLog()->getAll(); // Return all notifications

        return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/execengine/run', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Check for required role
        $allowedRoles = $ampersandApp->getSettings()->get('rbac.adminRoles');
        if (!$ampersandApp->hasRole($allowedRoles)) {
            throw new Exception("You do not have access to run the exec engine", 403);
        }
        
        $transaction = $ampersandApp->newTransaction()->runExecEngine(true)->close();

        if ($transaction->isCommitted()) {
            $ampersandApp->userLog()->notice("Run completed");
        } else {
            $ampersandApp->userLog()->warning("Run completed but transaction not committed");
        }

        $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
        
        return $response->withJson($ampersandApp->userLog()->getAll(), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/ruleengine/evaluate/all', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        
        // Check for required role
        $allowedRoles = $ampersandApp->getSettings()->get('rbac.adminRoles');
        if (!$ampersandApp->hasRole($allowedRoles)) {
            throw new Exception("You do not have access to evaluate all rules", 403);
        }

        foreach (Conjunct::getAllConjuncts() as $conj) {
            /** @var \Ampersand\Rule\Conjunct $conj */
            $conj->evaluate()->persistCacheItem();
        }
        
        foreach (RuleEngine::getViolations(Rule::getAllInvRules()) as $violation) {
            $ampersandApp->userLog()->invariant($violation);
        }
        foreach (RuleEngine::getViolations(Rule::getAllSigRules()) as $violation) {
            $ampersandApp->userLog()->signal($violation);
        }
        
        return $response->withJson($ampersandApp->userLog()->getAll(), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/export/all', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Export not allowed in production environment", 403);
        }
        
        // Export population to response body
        $exporter = new Exporter(new JSONWriter($response->getBody()), Logger::getLogger('IO'));
        $exporter->exportAllPopulation();

        // Return response
        $filename = $ampersandApp->getName() . "_population_" . date('Y-m-d\TH-i-s') . ".json";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    $this->post('/import', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];
        
        // Check for required role
        $allowedRoles = $ampersandApp->getSettings()->get('rbac.importerRoles');
        if (!$ampersandApp->hasRole($allowedRoles)) {
            throw new Exception("You do not have access to import population", 403);
        }
        
        // Check if there is a file uploaded
        if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
            throw new Exception("No file uploaded", 400);
        }

        $transaction = $ampersandApp->newTransaction();

        // Determine and execute import method based on extension.
        $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        switch ($extension) {
            case 'json':
                $reader = new JSONReader();
                $reader->loadFile($_FILES['file']['tmp_name']);
                $importer = new Importer($reader, Logger::getLogger('IO'));
                $importer->importPopulation();
                break;
            case 'xls':
            case 'xlsx':
            case 'ods':
                $importer = new ExcelImporter(Logger::getLogger('IO'));
                $importer->parseFile($_FILES['file']['tmp_name']);
                break;
            default:
                throw new Exception("Unsupported file extension", 400);
                break;
        }

        // Commit transaction
        $transaction->runExecEngine()->close();
        if ($transaction->isCommitted()) {
            $ampersandApp->userLog()->notice("Imported {$_FILES['file']['name']} successfully");
        }
        unlink($_FILES['file']['tmp_name']);
        
        // Check all process rules that are relevant for the activate roles
        $ampersandApp->checkProcessRules();

        // Return content
        $content = [ 'files'                 => $_FILES
                   , 'notifications'         => $ampersandApp->userLog()->getAll()
                   , 'invariantRulesHold'    => $transaction->invariantRulesHold()
                   , 'isCommitted'           => $transaction->isCommitted()
                   , 'sessionRefreshAdvice'  => $angularApp->getSessionRefreshAdvice()
                   ];
        $code = $transaction->isCommitted() ? 200 : 400; // 400 'Bad request' is used to trigger error in file uploader interface
        return $response->withJson($content, $code, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });
})->add($middleWare1);

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/admin/report', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('/relations', function (Request $request, Response $response, $args = []) {
        // Get report
        $reporter = new Reporter(new JSONWriter($response->getBody()));
        $reporter->reportRelationDefinitions();

        // Return reponse
        return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    $this->get('/conjuncts/usage', function (Request $request, Response $response, $args = []) {
        // Get report
        $reporter = new Reporter(new JSONWriter($response->getBody()));
        $reporter->reportConjunctUsage();

        // Return reponse
        return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    $this->get('/conjuncts/performance', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Get report
        $reporter = new Reporter(new CSVWriter($response->getBody()));
        $reporter->reportConjunctPerformance(Conjunct::getAllConjuncts());
        
        // Set response headers
        $filename = $ampersandApp->getName() . "_conjunct-performance_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    });

    $this->get('/interfaces', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Get report
        $reporter = new Reporter(new CSVWriter($response->getBody()));
        $reporter->reportInterfaceDefinitions();

        // Set response headers
        $filename = $ampersandApp->getName() . "_interface-definitions_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    });

    $this->get('/interfaces/issues', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Get report
        $reporter = new Reporter(new CSVWriter($response->getBody()));
        $reporter->reportInterfaceIssues();

        // Set response headers
        $filename = $ampersandApp->getName() . "_interface-issues_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    });
})->add($middleWare1)->add(function (Request $req, Response $res, callable $next) {
    /** @var \Ampersand\AmpersandApp $ampersandApp */
    $ampersandApp = $this['ampersand_app'];

    if ($ampersandApp->getSettings()->get('global.productionEnv')) {
        throw new Exception("Reports are not allowed in production environment", 403);
    }

    return $next($req, $res);
});
