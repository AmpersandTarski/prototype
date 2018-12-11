<?php

use Ampersand\Core\Concept;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\InterfaceController;
use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Interfacing\ResourceList;
use Ampersand\Core\Atom;

/**
 * @var \Slim\Slim $api
 */
global $api;

/**************************************************************************************************
 *
 * resource calls WITHOUT interfaces
 *
 *************************************************************************************************/

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/resource', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        if ($ampersandApp->getSettings()->get('global.productionEnv')) {
            throw new Exception("List of all resource types is not available in production environment", 403);
        }
        
        $content = array_values(
            array_map(function ($cpt) {
                return $cpt->label; // only show label of resource types
            }, array_filter(Concept::getAllConcepts(), function ($cpt) {
                return $cpt->isObject(); // filter concepts without a representation (i.e. resource types)
            }))
        );
        
        return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/{resourceType}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        
        // TODO: refactor when resources (e.g. for update field in UI) can be requested with interface definition
        $resources = ResourceList::makeWithoutInterface($args['resourceType']);
        
        return $response->withJson($resources->get(), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->post('/{resourceType}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $resource = ResourceList::makeWithoutInterface($args['resourceType'])->post();
        
        // Don't save/commit new resource (yet)
        // Transaction is not closed

        // Response
        return $response->withJson($resource->get(), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/{resourceType}/{resourceId}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $resource = ResourceList::makeWithoutInterface($args['resourceType'])->one($args['resourceId']);

        return $response->withJson($resource->get(), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    // GET for interfaces that start with other resource
    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/{resourceType}/{resourceId}/{ifcPath:.*}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        // Input
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');
        
        // Prepare
        $controller = new InterfaceController($ampersandApp, $angularApp);
        $src = Atom::makeAtom($args['resourceId'], $args['resourceType']);

        // Output
        return $response->withJson($controller->get($src, $args['ifcPath'], $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    // PUT, PATCH, POST for interfaces that start with other resource
    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->map(['PUT', 'PATCH', 'POST'], '/{resourceType}/{resourceId}[/{ifcPath:.*}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        // Input
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');
        $body = $request->reparseBody()->getParsedBody();
        $ifcPath = $args['ifcPath'];
        
        // Prepare
        $controller = new InterfaceController($ampersandApp, $angularApp);
        $src = Atom::makeAtom($args['resourceId'], $args['resourceType']);

        // Output
        switch ($request->getMethod()) {
            case 'PUT':
                return $response->withJson($controller->put($src, $ifcPath, $body, $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            case 'PATCH':
                return $response->withJson($controller->patch($src, $ifcPath, $body, $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            case 'POST':
                return $response->withJson($controller->post($src, $ifcPath, $body, $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            default:
                throw new Exception("Unsupported HTTP method", 500);
        }
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->delete('/{resourceType}/{resourceId}[/{ifcPath:.*}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        // Prepare
        $controller = new InterfaceController($ampersandApp, $angularApp);
        $src = Atom::makeAtom($args['resourceId'], $args['resourceType']);

        return $response->withJson($controller->delete($src, $args['ifcPath']), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });
})->add($middleWare1);

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/session', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    // GET for interfaces with expr[SESSION*..]
    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('[/{ifcPath:.*}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        // Input
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');

        // Prepare
        $controller = new InterfaceController($ampersandApp, $angularApp);
        $session = $ampersandApp->getSession()->getSessionAtom();

        // Output
        return $response->withJson($controller->get($session, $args['ifcPath'], $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });

    // PUT, PATCH, POST for interfaces with expr[SESSION*..]
    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->map(['PUT', 'PATCH', 'POST'], '[/{ifcPath:.*}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        // Input
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');
        $body = $request->reparseBody()->getParsedBody();
        $ifcPath = $args['ifcPath'];
        
        // Prepare
        $controller = new InterfaceController($ampersandApp, $angularApp);
        $session = $ampersandApp->getSession()->getSessionAtom();

        // Output
        switch ($request->getMethod()) {
            case 'PUT':
                return $response->withJson($controller->put($session, $ifcPath, $body, $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            case 'PATCH':
                return $response->withJson($controller->patch($session, $ifcPath, $body, $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            case 'POST':
                return $response->withJson($controller->post($session, $ifcPath, $body, $options, $depth), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            default:
                throw new Exception("Unsupported HTTP method", 500);
        }
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->delete('[/{ifcPath:.*}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        $session = $ampersandApp->getSession()->getSessionAtom();

        $controller = new InterfaceController($ampersandApp, $angularApp);

        return $response->withJson($controller->delete($session, $args['ifcPath']), 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    });
})->add($middleWare1);
