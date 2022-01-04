<?php

use Ampersand\API\Handler\ExceptionHandler;
use Ampersand\API\Handler\NotFoundHandler;
use Ampersand\API\Handler\PhpErrorHandler;
use Ampersand\API\Middleware\InitAmpersandAppMiddleware;
use Ampersand\API\Middleware\JsonRequestParserMiddleware;
use Ampersand\API\Middleware\LogPerformanceMiddleware;
use Ampersand\API\Middleware\PostMaxSizeMiddleware;
use Ampersand\Log\Logger;
use Slim\App;
use Slim\Container;

$scriptStartTime = (float) microtime(true);

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

$logger = Logger::getLogger('API');

$api
->add(new LogPerformanceMiddleware($logger, 'PHASE-4 REQUEST | ')) // wrapper to log performance of request phase (PHASE-4)
->add(new InitAmpersandAppMiddleware($ampersandApp, $logger)) // initialize the AmpersandApp (PHASE-2) and Session (PHASE-3)
->add(new PostMaxSizeMiddleware()) // catch when post_max_size is exceeded
->add(new JsonRequestParserMiddleware()) // overwrite default media type parser for application/json
->add(new LogPerformanceMiddleware($logger, 'TOTAL PERFORMANCE | ', $scriptStartTime)) // wrapper to log total performance
->run();
