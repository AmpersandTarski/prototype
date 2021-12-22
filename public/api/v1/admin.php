<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

use Ampersand\Log\Logger;
use Ampersand\IO\ExcelImporter;
use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Session;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Ampersand\Core\Population;
use Ampersand\Exception\UploadException;
use Ampersand\Controller\ExecEngineController;
use Ampersand\Controller\ReportController;
use Ampersand\Controller\RuleEngineController;

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

        $account = $ampersandApp->getModel()->getConceptByLabel('Account')->makeAtom($args['accountId']);

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

        Session::deleteExpiredSessions($ampersandApp);

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

        if (!$ampersandApp->getModel()->getConcept($resourceType)->isObject()) {
            throw new Exception("Resource type not found", 404);
        }
        
        $list = $request->reparseBody()->getParsedBody();
        if (!is_array($list)) {
            throw new Exception("Body must be array. Non-array provided", 500);
        }

        $transaction = $ampersandApp->newTransaction();

        foreach ($list as $item) {
            $atom = $ampersandApp->getModel()->getConceptByLabel($resourceType)->makeAtom($item->oldId);
            $atom->rename($item->newId);
        }
        
        $transaction->runExecEngine()->close();

        return $response->withJson($list, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/execengine/run', ExecEngineController::class . ':run');

    $this->get('/ruleengine/evaluate/all', RuleEngineController::class . ':evaluateAllRules');

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
        
        $fileInfo = $_FILES['file'];
        
        // Check if there is a file uploaded
        if (!is_uploaded_file($fileInfo['tmp_name'])) {
            throw new UploadException($fileInfo['error']);
        }

        $transaction = $ampersandApp->newTransaction();

        // Determine and execute import method based on extension.
        $extension = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
        switch ($extension) {
            case 'json':
                $decoder = new JsonDecode(false);
                $populationContent = $decoder->decode(file_get_contents($fileInfo['tmp_name']), JsonEncoder::FORMAT);
                $population = new Population($ampersandApp->getModel(), Logger::getLogger('IO'));
                $population->loadFromPopulationFile($populationContent);
                $population->import();
                break;
            case 'xls':
            case 'xlsx':
            case 'ods':
                $importer = new ExcelImporter($ampersandApp, Logger::getLogger('IO'));
                $importer->parseFile($fileInfo['tmp_name']);
                break;
            default:
                throw new Exception("Unsupported file extension", 400);
                break;
        }

        // Commit transaction
        $transaction->runExecEngine()->close();
        if ($transaction->isCommitted()) {
            $ampersandApp->userLog()->notice("Imported {$fileInfo['name']} successfully");
        }
        unlink($fileInfo['tmp_name']);
        
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

    $this->get('/relations', ReportController::class . ':reportRelations');

    $this->get('/conjuncts/usage', ReportController::class . ':conjunctUsage');

    $this->get('/conjuncts/performance', ReportController::class . ':conjunctPerformance');

    $this->get('/interfaces', ReportController::class . ':interfaces');

    $this->get('/interfaces/issues', ReportController::class . ':interfaceIssues');
});
