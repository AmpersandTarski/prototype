<?php

use Ampersand\Log\Logger;
use Ampersand\Log\NotificationHandler;
use Ampersand\Log\RequestIDProcessor;
use Ampersand\Misc\Config;
use Ampersand\AmpersandApp;
use Ampersand\Misc\Generics;
use Ampersand\AngularApp;

define('LOCALSETTINGS_VERSION', 1.7);
date_default_timezone_set('Europe/Amsterdam'); // see http://php.net/manual/en/timezones.php for a list of supported timezones
set_time_limit(30); // execution time limit is set to a default of 30 seconds. Use 0 to have no time limit. (not advised)

/**************************************************************************************************
 * LOGGING
 *************************************************************************************************/
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", false);

Config::set('debugMode', 'global', true); // default mode = false

// Log file handler
$fileHandler = new \Monolog\Handler\RotatingFileHandler(__DIR__ . '/log/error.log', 0, \Monolog\Logger::DEBUG);
// $fileHandler->pushProcessor(new RequestIDProcessor())->pushProcessor(new WebProcessor(null, [ 'ip' => 'REMOTE_ADDR', 'method' => 'REQUEST_METHOD', 'url' => 'REQUEST_URI'])); // Adds IP adres and url info to log records
$wrapper = new \Monolog\Handler\FingersCrossedHandler($fileHandler, \Monolog\Logger::ERROR, 0, true, true, \Monolog\Logger::WARNING);
Logger::registerGenericHandler($wrapper);

if (Config::get('debugMode')) {
    $fileHandler = new \Monolog\Handler\RotatingFileHandler(__DIR__ . '/log/debug.log', 0, \Monolog\Logger::DEBUG);
    Logger::registerGenericHandler($fileHandler);
    
    // Browsers debuggers
    //$browserHandler = new \Monolog\Handler\ChromePHPHandler(\Monolog\Logger::DEBUG); // Log handler for Google Chrome
    //$browserHandler = new \Monolog\Handler\FirePHPHandler(\Monolog\Logger::DEBUG); // Log handler for Firebug in Mozilla Firefox
    //Logger::registerGenericHandler($browserHandler);
}

$execEngineHandler = new \Monolog\Handler\RotatingFileHandler(__DIR__ . '/log/execengine.log', 0, \Monolog\Logger::DEBUG);
Logger::registerHandlerForChannel('EXECENGINE', $execEngineHandler);

// User log handler
Logger::registerHandlerForChannel('USERLOG', new NotificationHandler(\Monolog\Logger::INFO));

/**************************************************************************************************
 * APPLICATION
 *************************************************************************************************/
$logger = Logger::getLogger('APPLICATION');
$model = new Generics(Config::get('pathToGeneratedFiles'), $logger);
$ampersandApp = new AmpersandApp($model, $logger);
$angularApp = new AngularApp(Logger::getLogger('FRONTEND'));

/**************************************************************************************************
 * SERVER settings
 *************************************************************************************************/
// Config::set('serverURL', 'global', 'http://www.yourdomain.nl'); // defaults to http://localhost/<ampersand context name>
// Config::set('apiPath', 'global', '/api/v1'); // relative path to api

/**************************************************************************************************
 * DATABASE and PLUGS
 *************************************************************************************************/
$mysqlDB = new \Ampersand\Plugs\MysqlDB\MysqlDB(
    Config::get('dbHost', 'mysqlDatabase'),
    Config::get('dbUser', 'mysqlDatabase'),
    Config::get('dbPassword', 'mysqlDatabase'),
    Config::get('dbName', 'mysqlDatabase'),
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
