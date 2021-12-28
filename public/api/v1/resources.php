<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

use Ampersand\Controller\ResourceController;

/**
 * @var \Slim\Slim $api
 */
global $api;

/**************************************************************************************************
 *
 * resource calls WITHOUT interfaces
 *
 *************************************************************************************************/

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/resource', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('', ResourceController::class . ':listResourceTypes');
    $this->get('/{resourceType}', ResourceController::class . ':getAllResourcesForType');
    $this->post('/{resourceType}', ResourceController::class . ':createNewResourceId');
    $this->get('/{resourceType}/{resourceId}[/{resourcePath:.*}]', ResourceController::class . ':getResource');
    $this->map(['PUT', 'PATCH', 'POST'], '/{resourceType}/{resourceId}[/{ifcPath:.*}]', ResourceController::class . ':putPatchPostResource');
    $this->delete('/{resourceType}/{resourceId}[/{ifcPath:.*}]', ResourceController::class . ':deleteResource');
});
