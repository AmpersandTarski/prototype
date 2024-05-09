<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

use Ampersand\Controller\SessionController;
use Ampersand\API\Middleware\VerifyChecksumMiddleware;

global $ampersandApp;

/**
 * @var \Slim\Slim $api
 */
global $api;

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/app', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->patch('/roles', SessionController::class . ':updateRoles');
    $this->get('/navbar', SessionController::class . ':getNavMenu');
    $this->get('/notifications', SessionController::class . ':getNotifications');
})
->add(new VerifyChecksumMiddleware($ampersandApp));
