<?php

namespace Ampersand\API\Handler;

use Ampersand\AmpersandApp;
use Ampersand\Log\Logger;
use Error;
use function Ampersand\Misc\stackTrace;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

class PhpErrorHandler
{
    protected AmpersandApp $app;

    public function __construct(AmpersandApp $app)
    {
        $this->app = $app;
    }

    public function __invoke(Request $request, Response $response, Error $error)
    {
        try {
            Logger::getLogger("API")->critical(stackTrace($error));

            $debugMode = $this->app->getSettings()->get('global.debugMode');

            return $response->withJson(
                [ 'error' => 500
                , 'msg' => $debugMode ? $error->getMessage() : "An error occured. For more information see server log files"
                , 'notifications' => $this->app->userLog()->getAll()
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
    }
}
