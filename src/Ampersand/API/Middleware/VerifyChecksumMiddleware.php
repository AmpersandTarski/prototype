<?php

namespace Ampersand\API\Middleware;

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Ampersand\AmpersandApp;

class VerifyChecksumMiddleware
{
    protected AmpersandApp $app;

    public function __construct(AmpersandApp $app)
    {
        $this->app = $app;
    }

    public function __invoke(Request $request, ResponseInterface $response, callable $next): ResponseInterface
    {

        // Verify checksum
        // Must be done after init of storages and init of model (see above)
        if (!$this->app->verifyChecksum() && !$this->app->getSettings()->get('global.productionEnv')) {
            $this->app->userLog()->warning("Generated model is changed. You SHOULD reinstall or migrate your application");
        }

        return $next($request, $response);
    }
}
