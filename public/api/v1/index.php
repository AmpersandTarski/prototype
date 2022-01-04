<?php

use Ampersand\API\Handler\ExceptionHandler;
use Ampersand\API\Handler\NotFoundHandler;
use Ampersand\API\Handler\PhpErrorHandler;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\NotInstalledException;
use Ampersand\Exception\SessionExpiredException;
use Ampersand\Log\Logger;
use function Ampersand\Misc\humanFileSize;
use function Ampersand\Misc\returnBytes;
use Slim\App;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

$scriptStartTime = microtime(true);

require_once(dirname(__FILE__, 4) . '/bootstrap/framework.php');

/** @var \Ampersand\AmpersandApp $ampersandApp */
global $ampersandApp;

$apiContainer = new Container();
$apiContainer['ampersand_app'] = $ampersandApp; // add AmpersandApp object to API DI-container

$apiContainer['notFoundHandler'] = function ($container) {
    // Custom NotFound handler when API path-method is not found
    // The application can also return a Resource not found, this is handled by the errorHandler below
    return new NotFoundHandler();
};

$apiContainer['errorHandler'] = function ($container) use ($ampersandApp) {
    return new ExceptionHandler($ampersandApp);
};

$apiContainer['phpErrorHandler'] = function ($container) use ($ampersandApp) {
    return new PhpErrorHandler($ampersandApp);
};

// Settings
$apiContainer->get('settings')->replace(
    [ 'displayErrorDetails' => $ampersandApp->getSettings()->get('global.debugMode') // when true, additional information about exceptions are displayed by the default error handler
    , 'determineRouteBeforeAppMiddleware' => true // the route is calculated before any middleware is executed. This means that you can inspect route parameters in middleware if you need to.
    ]
);

// Create and configure Slim app (version 3.x)
$api = new App($apiContainer);

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
->add(function (Request $req, Response $res, callable $next) use ($ampersandApp) {
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
