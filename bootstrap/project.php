<?php

use Ampersand\Log\Logger;
use Ampersand\Misc\Settings;
use Ampersand\AmpersandApp;
use Ampersand\Model;
use Ampersand\AngularApp;
use Cascade\Cascade;

date_default_timezone_set('Europe/Amsterdam'); // see http://php.net/manual/en/timezones.php for a list of supported timezones
set_time_limit(30); // execution time limit is set to a default of 30 seconds. Use 0 to have no time limit. (not advised)

/**************************************************************************************************
 * LOGGING
 *************************************************************************************************/
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", false);

Cascade::fileConfig(dirname(__FILE__, 2) . '/config/logging.yaml'); // loads logging configuration

/**************************************************************************************************
 * APPLICATION
 *************************************************************************************************/
$logger = Logger::getLogger('APPLICATION');
$model = new Model(dirname(__FILE__, 2) . '/generics', $logger);

$settings = new Settings(); // includes default framework settings
$settings->loadSettingsFile($model->getFilePath('settings')); // load model settings from Ampersand generator
$settings->loadSettingsFile(dirname(__FILE__, 2) . '/config/projectSettings.json'); // load project specific settings
$settings->set('global.absolutePath', dirname(__FILE__));
$debugMode = $settings->get('global.debugMode');

$ampersandApp = new AmpersandApp($model, $settings, $logger);
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
