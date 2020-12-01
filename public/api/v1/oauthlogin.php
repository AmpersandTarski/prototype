<?php

use Ampersand\AmpersandApp;
use Ampersand\SIAM\OAuth\LoginController;
use Slim\Http\Request;
use Slim\Http\Response;
use function Ampersand\Misc\makeValidURL;

// Adds menu item to login/logout
/** @var \Ampersand\AngularApp $angularApp */
global $angularApp;
$angularApp->addMenuItem(
    'role',
    'app/src/oauthlogin/menuItem.html',
    function (AmpersandApp $app) {
        return $app->getSettings()->get('oauthlogin.enabled', false);
    }
);

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

    /**
     * @phan-closure-scope \Slim\Container
     */
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
                                         , 'redirect_uri' => makeValidUrl($idpSettings['redirectUrl'], $ampersandApp->getSettings()->get('global.serverURL'))
                                         , 'scope' => $idpSettings['scope']
                                         , 'state' => LoginController::getStateToken($ampersandApp)
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

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/logout', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $ampersandApp->logout();
        $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
        return $response->withJson(['notifications' => $ampersandApp->userLog()->getAll()], 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/callback/{idp}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        $settings = $ampersandApp->getSettings();
        
        $code = $request->getQueryParam('code');
        $state = $request->getQueryParam('state');
        $idp = $args['idp'];

        $identityProviders = $settings->get('oauthlogin.identityProviders');
        if (!isset($identityProviders[$idp])) {
            throw new Exception("Unsupported identity provider", 400);
        }

        if ($state !== LoginController::getStateToken($ampersandApp)) {
            throw new Exception("Invalid state parameter", 401);
        }

        // instantiate authController
        $authController = new LoginController(
            $identityProviders[$idp]['clientId'],
            $identityProviders[$idp]['clientSecret'],
            makeValidUrl($identityProviders[$idp]['redirectUrl'], $settings->get('global.serverURL')),
            $identityProviders[$idp]['tokenUrl'],
            $ampersandApp
        );

        $api_url = $identityProviders[$idp]['apiUrl'];
        $isLoggedIn = $authController->authenticate($code, $idp, $api_url);
        
        $url = $isLoggedIn ? $settings->get('oauthlogin.redirectAfterLogin') : $settings->get('oauthlogin.redirectAfterLoginFailure');
        $url = makeValidURL($url, $settings->get('global.serverURL')); // add serverUrl if url is specified as relative path

        return $response->withRedirect($url);
    });
})
/**
 * @phan-closure-scope \Slim\Container
 */
->add(function (Request $req, Response $res, callable $next) {
    /** @var \Ampersand\AmpersandApp $ampersandApp */
    $ampersandApp = $this['ampersand_app'];

    if (!$ampersandApp->getSettings()->get('oauthlogin.enabled', false)) {
        throw new Exception("The OAuth login module is not enabled", 501);
    }

    return $next($req, $res);
});
