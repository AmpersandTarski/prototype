<?php

use Ampersand\Misc\Config;
use Ampersand\AmpersandApp;

// Navigation menu settings
/** @var \Ampersand\AngularApp $angularApp */
global $angularApp;
$angularApp->addMenuItem(
    'refresh',
    'app/src/admin/installer-menu-item.html',
    function (AmpersandApp $app) {
        return !Config::get('productionEnv');
    }
);

$angularApp->addMenuItem(
    'refresh',
    'app/src/admin/check-rules-menu-item.html',
    function (AmpersandApp $app) {
        return !Config::get('productionEnv');
    }
);

$angularApp->addMenuItem(
    'refresh',
    'app/src/admin/execengine-menu-item.html',
    function (AmpersandApp $app) {
        $roles = Config::get('allowedRolesForRunFunction', 'execEngine');
        return $app->hasActiveRole($roles);
    }
);

$angularApp->addMenuItem(
    'ext',
    'app/src/importer/menu-item.html',
    function (AmpersandApp $app) {
        $roles = Config::get('allowedRolesForImporter');
        return $app->hasActiveRole($roles);
    }
);

$angularApp->addMenuItem(
    'ext',
    'app/src/admin/exporter-menu-item.html',
    function (AmpersandApp $app) {
        return !Config::get('productionEnv');
    }
);
