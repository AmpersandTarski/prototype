<?php

use Ampersand\Extension\OAuthLogin\OAuthLoginController;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @var \Slim\App $api
 */
global $api;

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/oauthlogin', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    $this->get('/login', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        // Get configured identity providers
        $identityProviders = $ampersandApp->getSettings()->get('oauthlogin.identityProviders');
        if (is_null($identityProviders)) {
            throw new Exception("No identity providers specified for OAuthLogin extension", 500);
        }
        if (!is_array($identityProviders)) {
            throw new Exception("Identity providers must be specified as array", 500);
        }
        
        // Prepare list with identity providers for the UI
        $idps = [];
        foreach ($identityProviders as $idpSettings) {
            $auth_url = [ 'auth_base' => $idpSettings['authBase']
                        , 'arguments' => [ 'client_id' => $idpSettings['clientId']
                                         , 'response_type' => 'code'
                                         , 'redirect_uri' => $idpSettings['redirectUrl']
                                         , 'scope' => $idpSettings['scope']
                                         , 'state' => $idpSettings['state']
                                         ]
                        ];
            $url = $auth_url['auth_base'] . '?' . http_build_query($auth_url['arguments']);
            
            $idps[] = [ 'name' => $idpSettings['name']
                      , 'loginUrl' => $url
                      , 'logo' => $idpSettings['logoUrl']
                      ];
        }
        
        return $response->withJson(['identityProviders' => $idps, 'notifications' => $ampersandApp->userLog()->getAll()], 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/logout', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $ampersandApp->logout();
        $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
        return $response->withJson(['notifications' => $ampersandApp->userLog()->getAll()], 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    $this->get('/callback/{idp}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        
        $code = $request->getQueryParam('code');
        $idp = $args['idp'];

        $identityProviders = $ampersandApp->getSettings()->get('oauthlogin.identityProviders');
        if (!isset($identityProviders[$idp])) {
            throw new Exception("Unsupported identity provider", 400);
        }

        // instantiate authController
        $authController = new OAuthLoginController(
            $identityProviders[$idp]['clientId'],
            $identityProviders[$idp]['clientSecret'],
            $identityProviders[$idp]['redirectUrl'],
            $identityProviders[$idp]['tokenUrl']
        );

        $api_url = $identityProviders[$idp]['apiUrl'];
        $isLoggedIn = $authController->authenticate($code, $idp, $api_url);
        
        if ($isLoggedIn) {
            $url = $ampersandApp->getSettings()->get('oauthlogin.redirectAfterLogin');
        } else {
            $url = $ampersandApp->getSettings()->get('oauthlogin.redirectAfterLoginFailure');
        }
        return $response->withRedirect($url);
    });
});
