<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

use Ampersand\Controller\OpenApiController;

/**
 * Publishes the compiler-generated OpenAPI description.
 *
 * These routes deliberately carry no session/checksum middleware, so external
 * tools (Swagger UI, Postman, codegen) can fetch the spec freely. Whether
 * anything is actually served is decided in OpenApiController: only for a
 * development build (not productionEnv) and only when openapi.json exists.
 *
 * @var \Slim\App $api
 */
global $api;

$api->get('/openapi.json', OpenApiController::class . ':getSpec');
$api->get('/docs', OpenApiController::class . ':getDocs');
