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
    $error = error_get_last();
    if ($error['type'] & (E_ERROR | E_PARSE)) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp;
        $debugMode = $ampersandApp->getSettings()->get('global.debugMode');

        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
        http_response_code(500);
        header("{$protocol} 500 Internal server error");
        print json_encode(['error' => 500
                          ,'msg' => "An error occurred"
                          ,'html' => $debugMode ? $error['message'] : null
                          ]);
        exit;
    }
});

date_default_timezone_set('Europe/Amsterdam'); // see http://php.net/manual/en/timezones.php for a list of supported timezones
set_time_limit(30); // execution time limit is set to a default of 30 seconds. Use 0 to have no time limit. (not advised)

/**************************************************************************************************
 * PHP SESSION (Start a new, or resume the existing, PHP session)
 *************************************************************************************************/
ini_set("session.use_strict_mode", true); // prevents a session ID that is never generated
ini_set("session.cookie_httponly", true); // ensures the cookie won't be accessible by scripting languages, such as JavaScript
if ($_SERVER['HTTPS'] ?? false) {
    ini_set("session.cookie_secure", true); // specifies whether cookies should only be sent over secure connections
}
session_start();

/**************************************************************************************************
 * COMPOSER AUTOLOADER
 *************************************************************************************************/
require_once(__DIR__ . '/../lib/autoload.php');

/**************************************************************************************************
 * LOGGING
 *************************************************************************************************/
error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", false);

Cascade::fileConfig(dirname(__FILE__, 2) . '/config/logging.yaml'); // loads logging configuration

/**************************************************************************************************
 * AMPERSAND APPLICATION
 *************************************************************************************************/
$logger = Logger::getLogger('APPLICATION');
$model = new Model(dirname(__FILE__, 2) . '/generics', $logger);

$settings = new Settings(); // includes default framework settings
$settings->loadSettingsJsonFile($model->getFilePath('settings')); // load model settings from Ampersand generator
$settings->loadSettingsYamlFile(dirname(__FILE__, 2) . '/config/projectSettings.yaml'); // load project specific settings
$settings->set('global.absolutePath', dirname(__FILE__));

$ampersandApp = new AmpersandApp($model, $settings, $logger);
$angularApp = new AngularApp($ampersandApp, Logger::getLogger('FRONTEND'));

/**************************************************************************************************
 * DATABASE and PLUGS
 *************************************************************************************************/
$mysqlSettings = $settings->get('mysqlSettings');
$mysqlDB = new MysqlDB(
    $mysqlSettings->dbHost,
    $mysqlSettings->dbUser,
    $mysqlSettings->dbPass,
    $mysqlSettings->dbName,
    Logger::getLogger('DATABASE'),
    $settings->get('global.debugMode')
);
$ampersandApp->setDefaultStorage($mysqlDB);
$ampersandApp->setConjunctCache(new MysqlConjunctCache($mysqlDB));

/**************************************************************************************************
 * OTHER BOOTSTRAPPING FILES (e.g. ExecEngine functions)
 *************************************************************************************************/
chdir(__DIR__);
foreach (glob('files/*.php') as $filepath) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . $filepath);
}
