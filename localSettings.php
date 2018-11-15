<?php

use Ampersand\Log\Logger;
use Ampersand\Log\NotificationHandler;
use Ampersand\Log\RequestIDProcessor;
use Ampersand\Misc\Config;
use Ampersand\AmpersandApp;
use Ampersand\Model;
use Ampersand\AngularApp;
use Monolog\Logger as MonoLogger;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\WebProcessor;

define('LOCALSETTINGS_VERSION', 2.0);
date_default_timezone_set('Europe/Amsterdam'); // see http://php.net/manual/en/timezones.php for a list of supported timezones
set_time_limit(30); // execution time limit is set to a default of 30 seconds. Use 0 to have no time limit. (not advised)

/**************************************************************************************************
 * LOGGING
 *************************************************************************************************/
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", false);

Config::set('debugMode', 'global', $debugMode = true); // default mode = false

// Add all channels to error and debug log file handlers
$errorLog = new RotatingFileHandler(__DIR__ . '/log/error.log', 0, MonoLogger::DEBUG);
// $errorLog->pushProcessor(new RequestIDProcessor())->pushProcessor(new WebProcessor(null, [ 'ip' => 'REMOTE_ADDR', 'method' => 'REQUEST_METHOD', 'url' => 'REQUEST_URI'])); // Adds IP adres and url info to log records
$errorLog = new FingersCrossedHandler($errorLog, MonoLogger::ERROR, 0, true, true, MonoLogger::WARNING);
$debugLog = new RotatingFileHandler(__DIR__ . '/log/debug.log', 0, MonoLogger::DEBUG);
Logger::setFactoryFunction(function ($channel) use ($errorLog, $debugLog, $debugMode) {
    $handlers[] = $errorLog;
    if ($debugMode) {
        $handlers[] = $debugLog;
    }
    return new MonoLogger($channel, $handlers);
});

// ExecEngine log
Logger::getLogger('EXECENGINE')->pushHandler(new RotatingFileHandler(__DIR__ . '/log/execengine.log', 0, MonoLogger::DEBUG));

// User interface logging
Logger::getUserLogger()->pushHandler(new NotificationHandler(MonoLogger::INFO));

/**************************************************************************************************
 * APPLICATION
 *************************************************************************************************/
$logger = Logger::getLogger('APPLICATION');
$model = new Model(dirname(__FILE__) . '/generics', $logger);
$ampersandApp = new AmpersandApp($model, $logger);
$angularApp = new AngularApp(Logger::getLogger('FRONTEND'));

/**************************************************************************************************
 * SERVER settings
 *************************************************************************************************/
// Config::set('serverURL', 'global', 'http://www.yourdomain.nl'); // defaults to http://localhost
// Config::set('apiPath', 'global', '/api/v1'); // relative path to api

/**************************************************************************************************
 * DATABASE and PLUGS
 *************************************************************************************************/
$mysqlDB = new \Ampersand\Plugs\MysqlDB\MysqlDB(
    $model->getSetting('mysqlSettings')->dbHost,
    $model->getSetting('mysqlSettings')->dbUser,
    $model->getSetting('mysqlSettings')->dbPass,
    $model->getSetting('mysqlSettings')->dbName,
    Logger::getLogger('DATABASE')
);
$ampersandApp->setDefaultStorage($mysqlDB);
$ampersandApp->setConjunctCache(new \Ampersand\Plugs\MysqlConjunctCache\MysqlConjunctCache($mysqlDB));

/**************************************************************************************************
 * LOGIN FUNCTIONALITY
 *
 * The login functionality requires the ampersand SIAM module
 * The module can be downloaded at: https://github.com/AmpersandTarski/ampersand-models/tree/master/SIAM
 * Copy and rename the SIAM_Module-example.adl into SIAM_Module.adl
 * Include this file into your project
 * Uncomment the config setting below
 *************************************************************************************************/
// Config::set('loginEnabled', 'global', true);
// Config::set('loginPage', 'login', 'ext/Login');
// Config::set('allowedRolesForImporter', 'global', []); // list of roles that have access to the importer


/**************************************************************************************************
 * EXECENGINE
 *************************************************************************************************/
// Config::set('execEngineRoleNames', 'execEngine', ['ExecEngine']);
// Config::set('autoRerun', 'execEngine', true);
// Config::set('maxRunCount', 'execEngine', 10);
// chdir(__DIR__);
// foreach(glob('execfunctions/*.php') as $filepath) require_once(__DIR__ . DIRECTORY_SEPARATOR . $filepath);


/**************************************************************************************************
 * EXTENSIONS
 *************************************************************************************************/
