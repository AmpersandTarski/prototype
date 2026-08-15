<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

use Ampersand\Controller\SearchController;

/**
 * @var \Slim\App $api
 */
global $api;

/**************************************************************************************************
 *
 * Full-text search across all stored data (TType-aware). See SearchController for the design.
 *
 *************************************************************************************************/

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/search', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('', SearchController::class . ':search');
});
