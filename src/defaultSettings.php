<?php

use Ampersand\Misc\Config;
use Ampersand\AngularApp;

try{
    Config::set('pathToGeneratedFiles', 'global', dirname(dirname(__FILE__)) . '/generics/');
    Config::set('pathToAppFolder', 'global', dirname(dirname(__FILE__)) . '/app/');
    
    // Load settings.json
    $settings = file_get_contents(Config::get('pathToGeneratedFiles') . DIRECTORY_SEPARATOR . 'settings.json');
    $settings = json_decode($settings, true);

    // Settings
    Config::set('versionInfo', 'global', $settings['versionInfo']); // e.g. "Ampersand v3.2.0[master:acbd148], build time: 07-Nov-15 22:14:00 W. Europe Standard Time"
    Config::set('contextName', 'global', $settings['contextName']); // set the name of the application context

    // Mysql settings,  can be overwritten in localSettings.php
    Config::set('dbHost', 'mysqlDatabase', $settings['mysqlSettings']['dbHost']);
    Config::set('dbUser', 'mysqlDatabase', $settings['mysqlSettings']['dbUser']);
    Config::set('dbPassword', 'mysqlDatabase', $settings['mysqlSettings']['dbPass']);
    Config::set('dbName', 'mysqlDatabase', $settings['mysqlSettings']['dbName']);
    Config::set('dbsignalTableName', 'mysqlDatabase', $settings['mysqlSettings']['dbsignalTableName']);

    // Other default configuration
    Config::set('serverURL', 'global', 'http://localhost/' . Config::get('contextName')); // set the base url for the application
    Config::set('apiPath', 'global', '/api/v1'); // relative path to api

    Config::set('sessionExpirationTime', 'global', 60*60); // expiration time in seconds
    Config::set('productionEnv', 'global', false); // set environment as production deployment (or not = default)
    Config::set('debugMode', 'global', false); // set debugMode (or not = default). Impacts the way errors are returned by API

    Config::set('absolutePath', 'global', dirname(__DIR__) . DIRECTORY_SEPARATOR);
    Config::set('uploadPath', 'global', 'uploads/');
    Config::set('logPath', 'global', 'log/');
    Config::set('allowedMimeTypes', 'global', array('application/vnd.ms-excel'
            ,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ,'application/excel'
            ,'application/pdf'
            ,'text/xml'
    ));
    Config::set('allowedRolesForImporter', 'global', null); // implies that everyone has access

    Config::set('loginEnabled', 'global', false); // enable/disable login functionality (requires Ampersand script, see localSettings.php)


    Config::set('ignoreInvariantViolations', 'transactions', false); // for debugging can be set to true (transactions will be committed regardless off invariant violations)
    Config::set('skipUniInjConjuncts', 'transactions', false); // TODO: remove after fix for issue #535
    Config::set('interfaceAutoCommitChanges', 'transactions', true); // specifies whether changes in an interface are automatically commited when allowed (all invariants hold)
    Config::set('interfaceAutoSaveChanges', 'transactions', true); // specifies whether changes in interface are directly communicated (saved) to server
    Config::set('interfaceCacheGetCalls', 'transactions', false); // specifies whether GET calls should be cached by the frontend (e.g. angular) application

    // Default CRUD rights for interfaces
    Config::set('defaultCrudC', 'transactions', true);
    Config::set('defaultCrudR', 'transactions', true);
    Config::set('defaultCrudU', 'transactions', true);
    Config::set('defaultCrudD', 'transactions', true);

    // Default notification settings
    Config::set('defaultShowSignals', 'notifications', true);
    Config::set('defaultShowInfos', 'notifications', true);
    Config::set('defaultShowWarnings', 'notifications', true);
    Config::set('defaultShowSuccesses', 'notifications', true);
    Config::set('defaultAutoHideSuccesses', 'notifications', true);
    Config::set('defaultShowErrors', 'notifications', true);
    Config::set('defaultShowInvariants', 'notifications', true);

    // ExecEngine settings
    Config::set('execEngineRoleNames', 'execEngine', ['ExecEngine']);
    Config::set('autoRerun', 'execEngine', true);
    Config::set('maxRunCount', 'execEngine', 10);
    
    // Navigation menu settings
    AngularApp::addMenuItem('refresh', 'app/views/menu/installer.html', 
        function($app){
            return !Config::get('productionEnv');
        });
    
    AngularApp::addMenuItem('refresh', 'app/views/menu/checkAllRules.html',
        function($app){
            return !Config::get('productionEnv');
        });
    
    AngularApp::addMenuItem('refresh', 'app/views/menu/execEngine.html',
        function(\Ampersand\AmpersandApp $app){
            $roles = Config::get('allowedRolesForRunFunction','execEngine');
            return $app->hasActiveRole($roles);
        });

    AngularApp::addMenuItem('ext', 'app/views/menu/importer.html', 
        function(\Ampersand\AmpersandApp $app){
            $roles = Config::get('allowedRolesForImporter');
            return $app->hasActiveRole($roles);
        });
    
    AngularApp::addMenuItem('ext', 'app/views/menu/exporter.html',
        function($app){
            return !Config::get('productionEnv');
        });

}catch(Exception $e){
    throw $e;
}
