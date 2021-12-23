<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

use Ampersand\Controller\ExecEngineController;
use Ampersand\Controller\InstallerController;
use Ampersand\Controller\LoginController;
use Ampersand\Controller\PopulationController;
use Ampersand\Controller\ReportController;
use Ampersand\Controller\ResourceController;
use Ampersand\Controller\RuleEngineController;
use Ampersand\Controller\SessionController;

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

    $this->get('/test/login/{accountId}', LoginController::class . ':loginTest');
    $this->get('/sessions/delete/expired', SessionController::class . ':deleteExpiredSessions');
    $this->post('/resource/{resourceType}/rename', ResourceController::class . ':renameAtoms');
    $this->get('/execengine/run', ExecEngineController::class . ':run');
    $this->get('/ruleengine/evaluate/all', RuleEngineController::class . ':evaluateAllRules');
    $this->post('/import', PopulationController::class . ':importPopulationFromUpload');
})->add($middleWare1);

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/admin/installer', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('', InstallerController::class . ':install')->setName('applicationInstaller');
    $this->get('/metapopulation', InstallerController::class . ':installMetaPopulation');
    $this->get('/navmenu', InstallerController::class . ':installNavmenu');
    $this->get('/checksum/update', InstallerController::class . ':updateChecksum')->setName('updateChecksum');
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

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/admin/utils', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('/regenerate-all-atom-ids[/{concept}]', ResourceController::class . ':regenerateAtomIds');
});
