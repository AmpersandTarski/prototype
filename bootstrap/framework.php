<?php

use Ampersand\AmpersandApp;
use Ampersand\AngularApp;
use Ampersand\Log\Logger;
use Ampersand\Misc\Settings;
use Ampersand\Model;
use Ampersand\Plugs\MysqlConjunctCache\MysqlConjunctCache;
use Ampersand\Plugs\MysqlDB\MysqlDB;
use Cascade\Cascade;

register_shutdown_function(function () {
    /** @var array|null $error */
    $error = error_get_last();
    if (isset($error) && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        global $debugMode; // $debugMode is set below after loading setting files

        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
        http_response_code(500);
        header("{$protocol} 500 Internal server error");
        print json_encode(['error' => 500
                          ,'msg' => "An error occurred"
                          ,'html' => $debugMode ? $error['message'] : "See php.log for more information"
                          ]);
        exit;
    }
});

/**************************************************************************************************
 * PHP SESSION (Start a new, or resume the existing, PHP session)
 *************************************************************************************************/
ini_set("session.use_strict_mode", '1'); // prevents a session ID that is never generated
ini_set("session.cookie_httponly", '1'); // ensures the cookie won't be accessible by scripting languages, such as JavaScript
if ($_SERVER['HTTPS'] ?? false) {
    ini_set("session.cookie_secure", '1'); // specifies whether cookies should only be sent over secure connections
}
session_start();

/**************************************************************************************************
 * COMPOSER AUTOLOADER
 *************************************************************************************************/
require_once(__DIR__ . '/../lib/autoload.php');

/**************************************************************************************************
 * LOGGING
 *************************************************************************************************/
// PHP log
ini_set('error_reporting', E_ALL & ~E_NOTICE);
ini_set("display_errors", '0');
ini_set("log_errors", '1');
ini_set("error_log", dirname(__FILE__, 2) . '/log/php.log');

// Application log
Cascade::fileConfig(dirname(__FILE__, 2) . '/config/logging.yaml'); // loads logging configuration

/**************************************************************************************************
 * AMPERSAND APPLICATION
 *************************************************************************************************/
$logger = Logger::getLogger('APPLICATION');
$model = new Model(dirname(__FILE__, 2) . '/generics', $logger);

$settings = new Settings(); // includes default framework settings
$settings->set('global.absolutePath', dirname(__FILE__, 2));
$settings->loadSettingsJsonFile($model->getFilePath('settings')); // load model settings from Ampersand generator
$settings->loadSettingsYamlFile(dirname(__FILE__, 2) . '/config/project.yaml'); // load project specific settings
$debugMode = $settings->get('global.debugMode');

set_time_limit($settings->get('global.scriptTimeout'));
date_default_timezone_set($settings->get('global.defaultTimezone'));

$ampersandApp = new AmpersandApp($model, $settings, $logger);
$angularApp = new AngularApp($ampersandApp, Logger::getLogger('FRONTEND'));

/**************************************************************************************************
 * DATABASE and PLUGS
 *************************************************************************************************/
$mysqlDB = new MysqlDB(
    $settings->get('mysql.dbHost'),
    $settings->get('mysql.dbUser'),
    $settings->get('mysql.dbPass'),
    $settings->get('mysql.dbName'),
    Logger::getLogger('DATABASE'),
    $settings->get('global.debugMode')
);
$ampersandApp->setDefaultStorage($mysqlDB);
$ampersandApp->setConjunctCache(new MysqlConjunctCache($mysqlDB));

/**************************************************************************************************
 * OTHER BOOTSTRAPPING FILES (e.g. ExecEngine functions)
 *************************************************************************************************/
foreach (glob(__DIR__ . '/files/*.php') as $filepath) {
    require_once($filepath);
}
