<?php

use Ampersand\AmpersandApp;

// Includes
require_once(__DIR__ . '/src/OAuthLoginController.php');
require_once(__DIR__ . '/api/oauthlogin.php');

// UI
/** @var \Ampersand\AngularApp $angularApp */
global $angularApp;
$angularApp->addMenuItem(
    'role',
    'app/ext/OAuthLogin/views/MenuItem.html',
    function (AmpersandApp $app) {
        return true;
    }
);
