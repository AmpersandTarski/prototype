<?php

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

// PHP SESSION : Start a new, or resume the existing, PHP session
ini_set("session.use_strict_mode", true); // prevents a session ID that is never generated
ini_set("session.cookie_httponly", true); // ensures the cookie won't be accessible by scripting languages, such as JavaScript
if ($_SERVER['HTTPS'] ?? false) {
    ini_set("session.cookie_secure", true); // specifies whether cookies should only be sent over secure connections
}
session_start();

// Composer Autoloader
require_once(__DIR__ . '/../lib/autoload.php');

// Bootstrap project
require_once(__DIR__ . '/project.php');

// ExecEngine functions
chdir(__DIR__);
foreach(glob('execfunctions/*.php') as $filepath) require_once(__DIR__ . DIRECTORY_SEPARATOR . $filepath);
