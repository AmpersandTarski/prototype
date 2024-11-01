<?php

use Ampersand\AmpersandApp;
use Ampersand\Frontend\MenuItemRegistry;
use Ampersand\Frontend\MenuType;

MenuItemRegistry::addMenuItem(
    MenuType::EXT,
    'app/src/admin/installer-menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.adminRoles');
        return (!$app->getSettings()->get('global.productionEnv')) && $app->hasActiveRole($roles);
    }
);

MenuItemRegistry::addMenuItem(
    MenuType::EXT,
    'app/src/admin/check-rules-menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.adminRoles');
        return $app->hasActiveRole($roles);
    }
);

MenuItemRegistry::addMenuItem(
    MenuType::EXT,
    'app/src/admin/execengine-menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.adminRoles');
        return $app->hasActiveRole($roles);
    }
);

MenuItemRegistry::addMenuItem(
    MenuType::EXT,
    'app/src/importer/menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.importerRoles');
        return $app->hasActiveRole($roles);
    }
);

MenuItemRegistry::addMenuItem(
    MenuType::EXT,
    'app/src/admin/exporter-menu-item.html',
    function (AmpersandApp $app) {
        $roles = $app->getSettings()->get('rbac.adminRoles');
        return (!$app->getSettings()->get('global.productionEnv')) && $app->hasActiveRole($roles);
    }
);
