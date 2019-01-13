<?php

use Ampersand\AmpersandApp;

// Navigation menu settings
/** @var \Ampersand\AngularApp $angularApp */
global $angularApp;
$angularApp->addMenuItem(
    'refresh',
    'app/src/admin/installer-menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.adminRoles');
        return (!$app->getSettings()->get('global.productionEnv')) && $app->hasActiveRole($roles);
    }
);

$angularApp->addMenuItem(
    'refresh',
    'app/src/admin/check-rules-menu-item.html',
    function (AmpersandApp $app) {
        return !$app->getSettings()->get('rbac.adminRoles');
    }
);

$angularApp->addMenuItem(
    'refresh',
    'app/src/admin/execengine-menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.adminRoles');
        return $app->hasActiveRole($roles);
    }
);

$angularApp->addMenuItem(
    'ext',
    'app/src/importer/menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.importerRoles');
        return $app->hasActiveRole($roles);
    }
);

$angularApp->addMenuItem(
    'ext',
    'app/src/admin/exporter-menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.adminRoles');
        return (!$app->getSettings()->get('global.productionEnv')) && $app->hasActiveRole($roles);
    }
);
