<?php

use Ampersand\Log\Logger;
use Ampersand\Rule\Conjunct;
use Ampersand\Rule\Rule;
use Ampersand\Rule\RuleEngine;
use Ampersand\IO\Exporter;
use Ampersand\IO\Importer;
use Ampersand\IO\ExcelImporter;
use Ampersand\Misc\Reporter;
use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Session;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Ampersand\Core\Concept;
use Ampersand\Core\Atom;
use Ampersand\IO\RDFGraph;

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

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/test/login/{accountId}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Not allowed in production environment", 403);
        }

        if (!$ampersandApp->getSettings()->get('session.loginEnabled')) {
            throw new Exception("Testing login feature not applicable. Login functionality is not enabled", 400);
        }

        if (!isset($args['accountId'])) {
            throw new Exception("No account identifier 'accountId' provided", 400);
        }

        $account = Atom::makeAtom($args['accountId'], 'Account');

        $ampersandApp->login($account);
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
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
    
    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->post('/resource/{resourceType}/rename', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        
        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Not allowed in production environment", 403);
        }
        $resourceType = $args['resourceType'];

        if (!Concept::getConcept($resourceType)->isObject()) {
            throw new Exception("Resource type not found", 404);
        }
        
        $list = $request->reparseBody()->getParsedBody();
        if (!is_array($list)) {
            throw new Exception("Body must be array. Non-array provided", 500);
        }

        $transaction = $ampersandApp->newTransaction();

        foreach ($list as $item) {
            $atom = Atom::makeAtom($item->oldId, $resourceType);
            $atom->rename($item->newId);
        }
        
        $transaction->runExecEngine()->close();

        return $response->withJson($list, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
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

    /**
     * @phan-closure-scope \Slim\Container
     */
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

    /**
     * @phan-closure-scope \Slim\Container
     */
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

    /**
     * @phan-closure-scope \Slim\Container
     */
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
    $this->get('/metamodel', function (Request $request, Response $response, $args = []) {
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

    /**
     * @phan-closure-scope \Slim\Container
     */
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
                $decoder = new JsonDecode(false);
                $population = $decoder->decode(file_get_contents($_FILES['file']['tmp_name']), JsonEncoder::FORMAT);
                $importer = new Importer(Logger::getLogger('IO'));
                $importer->importPopulation($population);
                break;
            case 'xls':
            case 'xlsx':
            case 'ods':
                $importer = new ExcelImporter($ampersandApp, Logger::getLogger('IO'));
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

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/relations', function (Request $request, Response $response, $args = []) {
        // Get report
        $reporter = new Reporter(new JsonEncoder(), $response->getBody());
        $reporter->reportRelationDefinitions('json');

        // Return reponse
        return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/conjuncts/usage', function (Request $request, Response $response, $args = []) {
        // Get report
        $reporter = new Reporter(new JsonEncoder(), $response->getBody());
        $reporter->reportConjunctUsage('json');

        // Return reponse
        return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/conjuncts/performance', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Get report
        $reporter = new Reporter(new CsvEncoder(';', '"'), $response->getBody());
        $reporter->reportConjunctPerformance('csv', Conjunct::getAllConjuncts());
        
        // Set response headers
        $filename = $ampersandApp->getName() . "_conjunct-performance_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/interfaces', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Input
        $details = $request->getQueryParam('details', false);

        // Get report
        $reporter = new Reporter(new CsvEncoder(';', '"'), $response->getBody());
        if ($details) {
            $reporter->reportInterfaceObjectDefinitions('csv');
        } else {
            $reporter->reportInterfaceDefinitions('csv');
        }

        // Set response headers
        $filename = $ampersandApp->getName() . "_interface-definitions_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/interfaces/issues', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Get report
        $reporter = new Reporter(new CsvEncoder(';', '"'), $response->getBody());
        $reporter->reportInterfaceIssues('csv');

        // Set response headers
        $filename = $ampersandApp->getName() . "_interface-issues_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    });
})->add($middleWare1)->add(
    /**
     * @phan-closure-scope \Slim\Container
     */
    function (Request $req, Response $res, callable $next) {
        /** @var \Slim\Container $this */
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("Reports are not allowed in production environment", 403);
        }

        return $next($req, $res);
    }
);
