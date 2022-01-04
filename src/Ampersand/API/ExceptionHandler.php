<?php

namespace Ampersand\API;

use Ampersand\AmpersandApp;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\MethodNotAllowedException;
use Ampersand\Exception\NotFoundException;
use Ampersand\Exception\NotInstalledException;
use Ampersand\Exception\SessionExpiredException;
use Ampersand\Exception\UploadException;
use Ampersand\Log\Logger;
use Exception;
use function Ampersand\Misc\stackTrace;
use JsonException;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

class ExceptionHandler
{
    protected AmpersandApp $app;

    public function __construct(AmpersandApp $app)
    {
        $this->app = $app;
    }

    public function __invoke(Request $request, Response $response, Exception $exception)
    {
        try {
            $logger = Logger::getLogger("API");
            $debugMode = $this->app->getSettings()->get('global.debugMode');
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
                    if ($this->app->getSettings()->get('session.loginEnabled')) {
                        $data['loginPage'] = $this->app->getSettings()->get('session.loginPage'); // picked up by frontend to nav to login page
                    }
                    break;
                case $exception instanceof AccessDeniedException:
                    // If not yet authenticated, return 401 Unauthorized
                    if ($this->app->getSettings()->get('session.loginEnabled') && !$this->app->getSession()->sessionUserLoggedIn()) {
                        $code = 401; // Unauthorized
                        $message = "Please login to access this page";
                        $data['loginPage'] = $this->app->getSettings()->get('session.loginPage');
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
                , 'notifications' => $this->app->userLog()->getAll()
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
    }
}