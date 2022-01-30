<?php

namespace Ampersand\API\Middleware;

use Ampersand\Exception\BadRequestException;
use function Ampersand\Misc\humanFileSize;
use function Ampersand\Misc\returnBytes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PostMaxSizeMiddleware
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        // Only applies to POST requests with empty $_POST superglobal
        // See: https://www.php.net/manual/en/ini.core.php#ini.post-max-size
        if ($request->getMethod() === 'POST' && empty($_POST)) {
            // See if we can detect if post_max_size is exceeded
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $maxBytes = returnBytes(ini_get('post_max_size'));
                if ($_SERVER['CONTENT_LENGTH'] > $maxBytes) {
                    throw new BadRequestException("The request exceeds the maximum request size of " . humanFileSize($maxBytes));
                }
            }
        }

        return $next($request, $response);
    }
}
