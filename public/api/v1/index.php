<?php

use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\AtomNotFoundException;
use Ampersand\Log\Logger;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Container;

use function Ampersand\Misc\humanFileSize;
use function Ampersand\Misc\returnBytes;
use function Ampersand\Misc\stackTrace;
use Ampersand\Exception\NotInstalledException;
use Ampersand\Exception\SessionExpiredException;

$scriptStartTime = microtime(true);

require_once(dirname(__FILE__, 4) . '/bootstrap/framework.php');

/** @var \Ampersand\AmpersandApp $ampersandApp */
global $ampersandApp;

/** @var \Ampersand\AngularApp $angularApp */
global $angularApp;

$apiContainer = new Container();
$apiContainer['ampersand_app'] = $ampersandApp; // add AmpersandApp object to API DI-container
$apiContainer['angular_app'] = $angularApp; // add AngularApp object to API DI-container

// Custom NotFound handler when API path-method is not found
// The application can also return a Resource not found, this is handled by the errorHandler below
$apiContainer['notFoundHandler'] = function ($c) {
    return function (Request $request, Response $response) {
        $msg = "API path not found: {$request->getMethod()} {$request->getUri()}. Path is case sensitive";
        Logger::getLogger("API")->notice($msg);
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(
                [ 'error' => 404
                , 'msg' => $msg
                ]
            ));
    };
};

$apiContainer['errorHandler'] = function ($c) {
    return function (Request $request, Response $response, Exception $exception) use ($c) {
        try {
            /** @var \Ampersand\AmpersandApp $ampersandApp */
            $ampersandApp = $c['ampersand_app'];
            $logger = Logger::getLogger("API");
            $debugMode = $ampersandApp->getSettings()->get('global.debugMode');
            $data = []; // array for error context related data

            switch ($exception->getCode()) {
                case 401: // Unauthorized
                    $logger->notice($exception->getMessage());
                    $code = 401;
                    $message = $exception->getMessage();
                    if ($ampersandApp->getSettings()->get('session.loginEnabled')) {
                        $data['loginPage'] = $ampersandApp->getSettings()->get('session.loginPage'); // picked up by frontend to nav to login page
                    }
                    break;
                case 403: // Forbidden
                    $logger->notice($exception->getMessage());
                    // If not yet authenticated, return 401 Unauthorized
                    if ($ampersandApp->getSettings()->get('session.loginEnabled') && !$ampersandApp->getSession()->sessionUserLoggedIn()) {
                        $code = 401;
                        $message = "Please login to access this page";
                        $data['loginPage'] = $ampersandApp->getSettings()->get('session.loginPage');
                    // Else, return 403 Forbidden
                    } else {
                        $code = 403;
                        $message = "You do not have access to this page";
                    }
                    break;
                case 400: // Bad request
                case 404: // Not found
                    $logger->notice($exception->getMessage());
                    $code = $exception->getCode();
                    $message = $exception->getMessage();
                    break;
                default:
                    $logger->error($exception->getMessage());
                    $code = $exception->getCode();
                    // Only show exception message when application is in debug mode
                    $message = $debugMode ? $exception->getMessage() : "An error occured (debug information in server log files)";
                    break;
            }

            // Convert invalid HTTP status code to 500
            if (!is_integer($code) || $code < 100 || $code > 599) {
                $code = 500;
            }
            
            return $response->withJson(
                [ 'error' => $code
                , 'msg' => $message
                , 'data' => $data
                , 'notifications' => $ampersandApp->userLog()->getAll()
                , 'html' => $debugMode ? stackTrace($exception) : null
                ],
                $code,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable $throwable) { // catches both errors and exceptions
            Logger::getLogger("API")->critical($throwable->getMessage());
            return $response->withJson(
                [ 'error' => 500
                , 'msg' => "Something went wrong in returning an error message (debug information in server log files)"
                , 'html' => "Please contact the application administrator for more information"
                ],
                500,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        }
    };
};

$apiContainer['phpErrorHandler'] = function ($c) {
    return function (Request $request, Response $response, Error $error) use ($c) {
        try {
            Logger::getLogger("API")->critical($error->getMessage());
            /** @var \Ampersand\AmpersandApp $ampersandApp */
            $ampersandApp = $c['ampersand_app'];
            $debugMode = $ampersandApp->getSettings()->get('global.debugMode');

            return $response->withJson(
                [ 'error' => 500
                , 'msg' => $debugMode ? $error->getMessage() : "An error occured (debug information in server log files)"
                , 'notifications' => $ampersandApp->userLog()->getAll()
                , 'html' => $debugMode ? stackTrace($error) : "Please contact the application administrator for more information"
                ],
                500,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable $throwable) { // catches both errors and exceptions
            Logger::getLogger("API")->critical($throwable->getMessage());
            return $response->withJson(
                [ 'error' => 500
                , 'msg' => "Something went wrong in returning an error message (debug information in server log files)"
                , 'html' => "Please contact the application administrator for more information"
                ],
                500,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        }
    };
};

// Settings
$apiContainer->get('settings')->replace(
    [ 'displayErrorDetails' => $ampersandApp->getSettings()->get('global.debugMode') // when true, additional information about exceptions are displayed by the default error handler
    , 'determineRouteBeforeAppMiddleware' => true // the route is calculated before any middleware is executed. This means that you can inspect route parameters in middleware if you need to.
    ]
);

// Create and configure Slim app (version 3.x)
$api = new App($apiContainer);

// Add middleware to set default content type for response
$api->add(function (Request $req, Response $res, callable $next) {
    $res = $res->withHeader('Content-Type', 'application/json;charset=utf-8');
    $newResponse = $next($req, $res);
    return $newResponse;
});

$middleWare1 = function (Request $request, Response $response, callable $next) {
    // Overwrite default media type parser for application/json
    $request->registerMediaTypeParser('application/json', function ($input) {
        $data = json_decode($input, false); // set accoc param to false, this will return php stdClass object instead of array for json objects {}
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $data;
                break;
            case JSON_ERROR_DEPTH:
                throw new Exception("JSON error: Maximum stack depth exceeded", 400);
                break;
            case JSON_ERROR_STATE_MISMATCH:
                throw new Exception("JSON error: Underflow or the modes mismatch", 400);
                break;
            case JSON_ERROR_CTRL_CHAR:
                throw new Exception("JSON error: Unexpected control character found", 400);
                break;
            case JSON_ERROR_SYNTAX:
                throw new Exception("JSON error: Syntax error, malformed JSON", 400);
                break;
            case JSON_ERROR_UTF8:
                throw new Exception("JSON error: Malformed UTF-8 characters, possibly incorrectly encoded", 400);
                break;
            default:
                throw new Exception("JSON error: Unknown error in JSON content", 400);
                break;
        }
    });
    return $next($request, $response);
};

require_once(__DIR__ . '/resources.php'); // API calls starting with '/resource/'
require_once(__DIR__ . '/admin.php'); // API calls starting with '/admin/'
require_once(__DIR__ . '/admin.exporter.php'); // API calls starting with '/admin/exporter/'
require_once(__DIR__ . '/app.php'); // API calls starting with '/app/'
require_once(__DIR__ . '/files.php'); // API calls starting with '/file/'

foreach ($ampersandApp->getSettings()->getExtensions() as $ext) {
    $ext->bootstrap();
}

// Add middleware to initialize the AmpersandApp
/**
 * @phan-closure-scope \Slim\Container
 */
$api->add(function (Request $req, Response $res, callable $next) {
    $phase4StartTime = microtime(true);

    $response = $next($req, $res);

    // Report performance until here (i.e. REQUEST phase)
    $executionTime = round(microtime(true) - $phase4StartTime, 2);
    $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2); // Mb
    Logger::getLogger('PERFORMANCE')->debug("PHASE-4 REQUEST: Memory in use: {$memoryUsage} Mb");
    Logger::getLogger('PERFORMANCE')->debug("PHASE-4 REQUEST: Execution time  : {$executionTime} Sec");

    return $response;
})
// Add middleware to initialize the AmpersandApp
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $req, Response $res, callable $next) {
    /** @var \Slim\App $this */
    /** @var \Ampersand\AmpersandApp $ampersandApp */
    $ampersandApp = $this['ampersand_app'];

    $sessionIsResetFlag = false;
    
    try {
        $logger = Logger::getLogger('PERFORMANCE');
        
        // Report performance until here (i.e. PHASE-1 CONFIG)
        global $scriptStartTime;
        $executionTime = round(microtime(true) - $scriptStartTime, 2);
        $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2); // Mb
        $logger->debug("PHASE-1 CONFIG: Memory in use: {$memoryUsage} Mb");
        $logger->debug("PHASE-1 CONFIG: Execution time  : {$executionTime} Sec");

        // PHASE-2 INITIALIZATION OF AMPERSAND APP
        $phase2StartTime = microtime(true);

        $ampersandApp->init(); // initialize Ampersand application

        $executionTime = round(microtime(true) - $phase2StartTime, 2);
        $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2); // Mb
        $logger->debug("PHASE-2 INIT: Memory in use: {$memoryUsage} Mb");
        $logger->debug("PHASE-2 INIT: Execution time  : {$executionTime} Sec");
        
        // PHASE-3 SESSION INITIALIZATION
        $phase3StartTime = microtime(true);
        
        $ampersandApp->setSession(); // initialize session
        
        $executionTime = round(microtime(true) - $phase3StartTime, 2);
        $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2); // Mb
        $logger->debug("PHASE-3 SESSION: Memory in use: {$memoryUsage} Mb");
        $logger->debug("PHASE-3 SESSION: Execution time  : {$executionTime} Sec");
    } catch (NotInstalledException $e) {
        // Make sure to close any open transaction
        $ampersandApp->getCurrentTransaction()->cancel();
        
        if ($ampersandApp->getSettings()->get('global.debugMode')) {
            /** @var \Slim\Route $route */
            $route = $req->getAttribute('route');
            
            // If application installer API ROUTE is called, continue
            if (in_array($route->getName(), ['applicationInstaller', 'updateChecksum'], true)) {
                return $next($req, $res);
            // Else navigate user to /admin/installer page
            } else {
                return $res->withJson(
                    [ 'error' => 500
                    , 'msg' => $e->getMessage()
                    , 'html' => stackTrace($e)
                    , 'navTo' => "/admin/installer"
                    ],
                    500,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                );
            }
        } else {
            throw $e; // let error handler do the response.
        }
    } catch (SessionExpiredException $e) {
        // Automatically reset session and continue the application
        // This is more user-friendly then directly throwing a "Your session has expired" error to the user
        $ampersandApp->resetSession();
        $sessionIsResetFlag = true; // raise flag, which is used below
    }

    try {
        return $next($req, $res);
    } catch (Exception $e) {
        // If an exception is thrown in the application after the session is automatically reset, it is probably caused by this session reset (e.g. logout)
        if ($sessionIsResetFlag) {
            throw new Exception("Your session has expired", 401, $e); // map SessionExpiredException to HTTP 401 Unauthorized (not)
        } else {
            throw $e;
        }
    }
})
// Add middleware to transform Ampersand exceptions
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $req, Response $res, callable $next) {
    try {
        return $next($req, $res);
    } catch (AccessDeniedException $e) {
        throw new Exception($e->getMessage(), 403, $e); // Map to HTTP 403 - Forbidden
    } catch (AtomNotFoundException $e) {
        throw new Exception($e->getMessage(), 404, $e); // Map to HTTP 404 - Resource not found
    }
})
// Add middleware to catch when post_max_size is exceeded
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $req, Response $res, callable $next) {
    // Only applies to POST requests with empty $_POST superglobal
    // See: https://www.php.net/manual/en/ini.core.php#ini.post-max-size
    if ($req->isPost() && empty($_POST)) {
        // See if we can detect if post_max_size is exceeded
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $maxBytes = returnBytes(ini_get('post_max_size'));
            if ($_SERVER['CONTENT_LENGTH'] > $maxBytes) {
                throw new Exception("The request exceeds the maximum request size of " . humanFileSize($maxBytes), 400);
            }
        }
    }

    return $next($req, $res);
})->run();

$executionTime = round(microtime(true) - $scriptStartTime, 2);
$peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2); // Mb
Logger::getLogger('PERFORMANCE')->info("Peak memory used: {$peakMemory} Mb");
Logger::getLogger('PERFORMANCE')->info("Execution time  : {$executionTime} Sec");
