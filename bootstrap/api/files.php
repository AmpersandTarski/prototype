<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

use Ampersand\Controller\FileObjectController;

/**
 * @var \Slim\Slim $api
 */
global $api;

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/file', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('/{filePath:.*}', FileObjectController::class . ':getFile');
});
