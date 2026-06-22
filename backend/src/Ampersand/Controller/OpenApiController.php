<?php

namespace Ampersand\Controller;

use Ampersand\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Publishes the OpenAPI description that the Ampersand compiler generates.
 *
 * The compiler writes backend/generics/openapi.json only for a development build
 * and passes the build target on as `global.productionEnv`. This controller
 * therefore serves the spec (and a Swagger UI) only when NOT in production, and
 * only when the file is actually present. So compiler and framework stay
 * consistent: in production nothing is published, in development the spec is.
 */
class OpenApiController extends AbstractController
{
    /**
     * Absolute path to the compiler-generated spec.
     *
     * This controller lives in backend/src/Ampersand/Controller/, so
     * dirname(__FILE__, 4) is the backend directory (also in the container,
     * where backend is at /var/www/backend). The generics folder sits next to it.
     */
    private function specFile(): string
    {
        return dirname(__FILE__, 4) . '/generics/openapi.json';
    }

    /**
     * In production the spec is not published. We return a plain 404 (rather than
     * a 403) so the endpoint is indistinguishable from "not generated".
     */
    private function guardPublished(): void
    {
        if ($this->app->inProductionMode() || !file_exists($this->specFile())) {
            throw new NotFoundException("OpenAPI description is not available for this prototype");
        }
    }

    /**
     * Machine-readable spec at /api/v1/openapi.json. CORS is open so external
     * tooling (Swagger UI, Postman, codegen) can read it cross-origin.
     */
    public function getSpec(Request $request, Response $response, array $args): Response
    {
        $this->guardPublished();

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->write((string) file_get_contents($this->specFile()));
    }

    /**
     * Human-friendly Swagger UI at /api/v1/docs. It loads openapi.json relative
     * to itself, i.e. from /api/v1/openapi.json.
     */
    public function getDocs(Request $request, Response $response, array $args): Response
    {
        $this->guardPublished();

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>API documentation</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: 'openapi.json',
      dom_id: '#swagger-ui',
      deepLinking: true
    });
  </script>
</body>
</html>
HTML;

        return $response
            ->withHeader('Content-Type', 'text/html')
            ->write($html);
    }
}
