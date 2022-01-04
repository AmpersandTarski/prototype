<?php

namespace Ampersand\API\Middleware;

use Ampersand\Exception\BadRequestException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;

class JsonRequestParserMiddleware
{
    public function __invoke(Request $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        $request->registerMediaTypeParser('application/json', function ($input) {
            try {
                // Set accoc param to false, this will return php stdClass object instead of array for json objects {}
                return json_decode($input, false, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new BadRequestException("JSON error: {$e->getMessage()}", previous: $e);
            }
        });
        return $next($request, $response);
    }
}
