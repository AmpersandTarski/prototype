<?php

namespace Ampersand\API;

use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Log\Logger;

class NotFoundHandler
{
    public function __invoke(Request $request, Response $response) {
        $msg = "API path not found: {$request->getMethod()} {$request->getUri()}. Path is case sensitive";
        Logger::getLogger("API")->notice($msg);
        return $response->withJson(
            [ 'error' => 404
            , 'msg' => $msg
            ],
            404,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
