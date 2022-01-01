<?php

use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\MethodNotAllowedException;
use Ampersand\Exception\NotFoundException;
use Ampersand\Exception\NotInstalledException;
use Ampersand\Exception\SessionExpiredException;
use Ampersand\Exception\UploadException;
use Ampersand\Log\Logger;
use function Ampersand\Misc\humanFileSize;
use function Ampersand\Misc\returnBytes;
use function Ampersand\Misc\stackTrace;
use Slim\App;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

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
        return $response->withJson(
            [ 'error' => 404
            , 'msg' => $msg
            ],
            404,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
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

            switch (true) {
                case $exception instanceof BadRequestException:
                case $exception instanceof JsonException:
                    $code = 400; // Bad request
                    $message = $exception->getMessage();
                    break;
                case $exception instanceof SessionExpiredException:
                    $code = 401; // Unauthorized
                    $message = $exception->getMessage();
                    if ($ampersandApp->getSettings()->get('session.loginEnabled')) {
                        $data['loginPage'] = $ampersandApp->getSettings()->get('session.loginPage'); // picked up by frontend to nav to login page
                    }
                    break;
                case $exception instanceof AccessDeniedException:
                    // If not yet authenticated, return 401 Unauthorized
                    if ($ampersandApp->getSettings()->get('session.loginEnabled') && !$ampersandApp->getSession()->sessionUserLoggedIn()) {
                        $code = 401; // Unauthorized
                        $message = "Please login to access this page";
                        $data['loginPage'] = $ampersandApp->getSettings()->get('session.loginPage');
                    // Else, return 403 Forbidden
                    } else {
                        $code = 403; // Forbidden
                        $message = "You do not have access to this page";
                    }
                    break;
                case $exception instanceof NotFoundException:
                    $code = 404; // Not found
                    $message = $exception->getMessage();
                    break;
                case $exception instanceof MethodNotAllowedException:
                    $code = 405; // Method not allowed
                    $message = $exception->getMessage();
                    break;
                case $exception instanceof UploadException:
                    $code = $exception->getCode();
                    if ($code < 500) {
                        $message = $exception->getMessage();
                    } else {
                        $message = $debugMode ? $exception->getMessage() : "An error occured while uploading file. Please contact the application administrator";
                    }
                    break;
                case $exception instanceof NotInstalledException:
                    $code = 500; // Internal server error
                    $message = $debugMode ? "{$exception->getMessage()}. Try reinstalling the application" : "An error occured. For more information see server log files";
                    if ($debugMode) {
                        $data['navTo'] = "/admin/installer";
                    }
                    break;
                case $exception instanceof FatalException:
                    $message = $debugMode ? "A fatal exception occured. Please report the full stacktrace to the Ampersand development team on Github: {$exception->getMessage()}" : "A fatal error occured. For more information see server log files";
                    $code = 500; // Internal server error
                // Map all other (Ampersand) exceptions to 500 - Internal server error
                default:
                    $code = 500; // Internal server error
                    $message = $debugMode ? $exception->getMessage() : "An error occured. For more information see server log files";
                    break;
            }

            // Convert invalid HTTP status code to 500
            if (!is_integer($code) || $code < 100 || $code > 599) {
                $code = 500;
            }

            // Logging
            if ($code >= 500) {
                $logger->error(stackTrace($exception)); // For internal server errors we want the stacktrace to understand what's happening
            } else {
                $logger->notice($exception->getMessage()); // For user errors a notice of the exception message is sufficient
            }
            
            return $response->withJson(
                [ 'error' => $code
                , 'msg' => $message
                , 'notifications' => $ampersandApp->userLog()->getAll()
                , 'html' => $debugMode ? stackTrace($exception) : null
                , ...$data // @phan-suppress-current-line PhanTypeMismatchUnpackKeyArraySpread
                ],
                $code,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable $throwable) { // catches both errors and exceptions
            Logger::getLogger("API")->critical(stackTrace($throwable));
            return $response->withJson(
                [ 'error' => 500
                , 'msg' => "Something went wrong in returning an error message. For more information see server log files"
                , 'html' => "Please contact the application administrator"
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
            Logger::getLogger("API")->critical(stackTrace($error));

            /** @var \Ampersand\AmpersandApp $ampersandApp */
            $ampersandApp = $c['ampersand_app'];
            $debugMode = $ampersandApp->getSettings()->get('global.debugMode');

            return $response->withJson(
                [ 'error' => 500
                , 'msg' => $debugMode ? $error->getMessage() : "An error occured. For more information see server log files"
                , 'notifications' => $ampersandApp->userLog()->getAll()
                , 'html' => $debugMode ? stackTrace($error) : "Please contact the application administrator"
                ],
                500,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable $throwable) { // catches both errors and exceptions
            Logger::getLogger("API")->critical(stackTrace($throwable));

            return $response->withJson(
                [ 'error' => 500
                , 'msg' => "Something went wrong in returning an error message. For more information see server log files"
                , 'html' => "Please contact the application administrator"
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

require_once(__DIR__ . '/resources.php'); // API calls starting with '/resource/'
require_once(__DIR__ . '/admin.php'); // API calls starting with '/admin/'
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
        
        /** @var \Slim\Route $route */
        $route = $req->getAttribute('route');
        
        // If application installer API ROUTE is called, continue
        if (in_array($route->getName(), ['applicationInstaller', 'updateChecksum'], true)) {
            return $next($req, $res);
        } else {
            throw $e;
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
            throw new SessionExpiredException("Your session has expired", previous: $e);
        } else {
            throw $e;
        }
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
                throw new BadRequestException("The request exceeds the maximum request size of " . humanFileSize($maxBytes));
            }
        }
    }

    return $next($req, $res);
})
// Add middleware to overwrite default media type parser for application/json
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $request, Response $response, callable $next) {
    $request->registerMediaTypeParser('application/json', function ($input) {
        try {
            // Set accoc param to false, this will return php stdClass object instead of array for json objects {}
            return json_decode($input, false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new Exception("JSON error: {$e->getMessage()}", previous: $e);
        }
    });
    return $next($request, $response);
})
->run();

$executionTime = round(microtime(true) - $scriptStartTime, 2);
$peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2); // Mb
Logger::getLogger('PERFORMANCE')->info("Peak memory used: {$peakMemory} Mb");
Logger::getLogger('PERFORMANCE')->info("Execution time  : {$executionTime} Sec");
