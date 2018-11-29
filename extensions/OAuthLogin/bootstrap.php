<?php

// Includes
require('./src/OAuthLoginController.php');
require('./api/oauthlogin.php');

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
