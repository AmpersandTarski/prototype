<?php

namespace Ampersand\Controller;

use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Frontend\MenuType;

class SessionController extends AbstractController
{
    public function updateRoles(Request $request, Response $response, array $args): Response
    {
        $this->app->setActiveRoles((array) $request->getParsedBody());
        
        return $response->withJson(
            $this->app->getSessionRoles(),
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function getNavMenu(Request $request, Response $response, array $args): Response
    {
        $this->app->checkProcessRules();
        
        $session = $this->app->getSession();
        $settings = $this->app->getSettings();

        $content =  ['home' => $this->app->getSettings()->get('frontend.homePage')
                    ,'navs' => $this->angularApp->getNavMenuItems()
                    ,'new' => $this->angularApp->getMenuItems(MenuType::NEW)
                    ,'ext' => $this->angularApp->getMenuItems(MenuType::EXT)
                    ,'role' => $this->angularApp->getMenuItems(MenuType::ROLE)
                    ,'defaultSettings' => ['notify_showSignals'        => $settings->get('notifications.defaultShowSignals')
                                          ,'notify_showInfos'          => $settings->get('notifications.defaultShowInfos')
                                          ,'notify_showSuccesses'      => $settings->get('notifications.defaultShowSuccesses')
                                          ,'notify_autoHideSuccesses'  => $settings->get('notifications.defaultAutoHideSuccesses')
                                          ,'notify_showErrors'         => $settings->get('notifications.defaultShowErrors')
                                          ,'notify_showWarnings'       => $settings->get('notifications.defaultShowWarnings')
                                          ,'notify_showInvariants'     => $settings->get('notifications.defaultShowInvariants')
                                          ,'autoSave'                  => $settings->get('transactions.interfaceAutoSaveChanges')
                                          ]
                    ,'notifications' => $this->app->userLog()->getAll()
                    ,'session' =>   ['id' => $session->getId()
                                    ,'loggedIn' => $session->sessionUserLoggedIn()
                                    ]
                    ,'sessionRoles' => $this->app->getSessionRoles()
                    ,'sessionVars' => $session->getSessionVars()
                    ];
        
        return $response->withJson(
            $content,
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function getNotifications(Request $request, Response $response, array $args): Response
    {
        return $this->success($response);
    }
}
