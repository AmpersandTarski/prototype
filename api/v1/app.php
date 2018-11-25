<?php

use Slim\Http\Request;
use Slim\Http\Response;

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

    $this->patch('/roles', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $ampersandApp->setActiveRoles((array) $request->getParsedBody());
        return $response->withJson($ampersandApp->getSessionRoles(), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/navbar', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        $ampersandApp->checkProcessRules();
        
        $session = $ampersandApp->getSession();
        $settings = $ampersandApp->getSettings();
        $content =  ['top' => $angularApp->getMenuItems('top')
                    ,'new' => $angularApp->getMenuItems('new')
                    ,'refresh' => $angularApp->getMenuItems('refresh')
                    ,'ext' => $angularApp->getMenuItems('ext')
                    ,'role' => $angularApp->getMenuItems('role')
                    ,'defaultSettings' => ['notify_showSignals'        => $settings->get('notifications.defaultShowSignals')
                                          ,'notify_showInfos'          => $settings->get('notifications.defaultShowInfos')
                                          ,'notify_showSuccesses'      => $settings->get('notifications.defaultShowSuccesses')
                                          ,'notify_autoHideSuccesses'  => $settings->get('notifications.defaultAutoHideSuccesses')
                                          ,'notify_showErrors'         => $settings->get('notifications.defaultShowErrors')
                                          ,'notify_showWarnings'       => $settings->get('notifications.defaultShowWarnings')
                                          ,'notify_showInvariants'     => $settings->get('notifications.defaultShowInvariants')
                                          ,'autoSave'                  => $settings->get('notifications.interfaceAutoSaveChanges')
                                          ]
                    ,'notifications' => $ampersandApp->userLog()->getAll()
                    ,'session' =>   ['id' => $session->getId()
                                    ,'loggedIn' => $session->sessionUserLoggedIn()
                                    ]
                    ,'sessionRoles' => $ampersandApp->getSessionRoles()
                    ,'sessionVars' => $session->getSessionVars()
                    ];
        return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/notifications', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $ampersandApp->checkProcessRules();
        return $response->withJson($ampersandApp->userLog()->getAll(), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });
})->add($middleWare1);
