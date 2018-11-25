<?php

use Ampersand\Log\Logger;
use Ampersand\Log\NotificationHandler;
use Ampersand\Log\RequestIDProcessor;
use Ampersand\Misc\Settings;
use Ampersand\AmpersandApp;
use Ampersand\Model;
use Ampersand\AngularApp;
use Monolog\Logger as MonoLogger;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\WebProcessor;

date_default_timezone_set('Europe/Amsterdam'); // see http://php.net/manual/en/timezones.php for a list of supported timezones
set_time_limit(30); // execution time limit is set to a default of 30 seconds. Use 0 to have no time limit. (not advised)

$settings = new Settings(); // includes default framework settings
$settings->loadSettingsFile($model->getFilePath('settings')); // load model settings from Ampersand generator
$settings->loadSettingsFile(dirname(__FILE__, 2) . '/config/projectSettings.json'); // load project specific settings
$settings->set('global.absolutePath', dirname(__FILE__));

$debugMode = $settings->get('global.debugMode');

/**************************************************************************************************
 * LOGGING
 *************************************************************************************************/
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", false);

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
$ampersandApp = new AmpersandApp($model, $settings, $logger, Logger::getUserLogger());
$angularApp = new AngularApp($ampersandApp, Logger::getLogger('FRONTEND'));


/**************************************************************************************************
 * DATABASE and PLUGS
 *************************************************************************************************/
$mysqlDB = new \Ampersand\Plugs\MysqlDB\MysqlDB(
    $ampersandApp->getSettings()->get('mysqlSettings')->dbHost,
    $ampersandApp->getSettings()->get('mysqlSettings')->dbUser,
    $ampersandApp->getSettings()->get('mysqlSettings')->dbPass,
    $ampersandApp->getSettings()->get('mysqlSettings')->dbName,
    Logger::getLogger('DATABASE'),
    $debugMode
);
$ampersandApp->setDefaultStorage($mysqlDB);
$ampersandApp->setConjunctCache(new \Ampersand\Plugs\MysqlConjunctCache\MysqlConjunctCache($mysqlDB));

/**************************************************************************************************
 * EXTENSIONS
 *************************************************************************************************/
