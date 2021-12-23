<?php

use Ampersand\Controller\InstallerController;

/**
 * @var \Slim\App $api
 */
global $api;

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/admin/installer', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('', InstallerController::class . ':install')->setName('applicationInstaller');

    $this->get('/metapopulation', InstallerController::class . ':installMetaPopulation');

    $this->get('/navmenu', InstallerController::class . ':installNavmenu');

    $this->get('/checksum/update', InstallerController::class . ':updateChecksum')->setName('updateChecksum');
})->add($middleWare1);
