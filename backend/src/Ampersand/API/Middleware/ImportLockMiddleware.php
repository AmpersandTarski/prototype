<?php

namespace Ampersand\API\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ampersand\AmpersandApp;

/**
 * Gates the API while the prototype is locked in import-bootstrap mode
 * (AmpersandApp::isImportLocked()). Only the endpoints needed to import data, run the
 * check, and render the import screen stay reachable; every other request — notably the
 * application's own /resource interfaces — is answered with 423 Locked. This enforces the
 * lock server-side, not just in the UI, so the application's functionality is genuinely
 * unreachable until "Start checking" passes. See the "Importing Large Datasets" guide.
 */
class ImportLockMiddleware
{
    protected AmpersandApp $app;

    public function __construct(AmpersandApp $app)
    {
        $this->app = $app;
    }

    /**
     * Path prefixes (under the API root) reachable while locked: the session/nav and
     * frontend-boot endpoints (/app), and the admin tooling that does the import and the
     * check (/admin, which includes /admin/import, /admin/importmode/check, /admin/installer).
     */
    private const ALLOWED_PREFIXES = ['/api/v1/app/', '/api/v1/admin/'];

    /** Exact paths reachable while locked (the machine-readable spec and its UI). */
    private const ALLOWED_EXACT = ['/api/v1/openapi.json', '/api/v1/docs'];

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        if ($this->app->isImportLocked() && !$this->isAllowed($request->getUri()->getPath())) {
            $response->getBody()->write((string) json_encode(
                [ 'error' => 423
                , 'msg'   => "The application is locked: it is in import mode. Import your data and run 'Start checking'. The application starts once every invariant holds."
                ],
                JSON_UNESCAPED_SLASHES
            ));
            return $response->withStatus(423)->withHeader('Content-Type', 'application/json');
        }

        return $next($request, $response);
    }

    private function isAllowed(string $path): bool
    {
        foreach (self::ALLOWED_EXACT as $allowed) {
            if ($path === $allowed) {
                return true;
            }
        }
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
