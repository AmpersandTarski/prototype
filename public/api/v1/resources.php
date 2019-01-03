<?php

use Ampersand\Core\Concept;
use Ampersand\Interfacing\Options;
use Slim\Http\Request;
use Slim\Http\Response;
use Ampersand\Interfacing\ResourceList;
use Ampersand\Interfacing\ResourcePath;

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

    // GET for interfaces that start with other resource
    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/{resourceType}/{resourceId}[/{resourcePath:.*}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        // Input
        $pathList = ResourcePath::makePathList($args['resourcePath']);
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');

        // Prepare
        $resource = ResourceList::makeWithoutInterface($args['resourceType'])->one($args['resourceId']);

        // Output
        return $response->withJson(
            $resource->walkPath($pathList)->get($options, $depth),
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
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
        $pathList = ResourcePath::makePathList($args['ifcPath']);
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');
        $body = $request->reparseBody()->getParsedBody();

        // Prepare
        $transaction = $ampersandApp->newTransaction();
        $entry = ResourceList::makeWithoutInterface($args['resourceType'])->one($args['resourceId']);

        // Perform action
        switch ($request->getMethod()) {
            case 'PUT':
                $resource = $entry->walkPathToResource($pathList)->put($body);
                $successMessage = "{$resource->getLabel()} updated";
                break;
            case 'PATCH':
                $resource = $entry->walkPathToResource($pathList)->patch($body);
                $successMessage = "{$resource->getLabel()} updated";
                break;
            case 'POST':
                $resource = $entry->walkPathToList($pathList)->post($body);
                $successMessage = "{$resource->getLabel()} created";
                break;
            default:
                throw new Exception("Unsupported HTTP method", 500);
        }

        // Run ExecEngine
        $transaction->runExecEngine();

        // Get content to return
        try {
            $content = $resource->get($options, $depth);
        } catch (Exception $e) {
            switch ($e->getCode()) {
                case 403: // Access denied (e.g. PATCH on root node there is no interface specified)
                case 405: // Method not allowed (e.g. when read is not allowed)
                    $content = $request->getMethod() === 'PATCH' ? null : $body;
                    break;
                default:
                    throw $e;
                    break;
            }
        }
        
        // Close transaction
        $transaction->close();
        if ($transaction->isCommitted()) {
            $ampersandApp->userLog()->notice($successMessage);
        }
        $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles

        // Output
        return $response->withJson(
            [ 'content'               => $content
            , 'patches'               => $request->getMethod() === 'PATCH' ? $body : []
            , 'notifications'         => $ampersandApp->userLog()->getAll()
            , 'invariantRulesHold'    => $transaction->invariantRulesHold()
            , 'isCommitted'           => $transaction->isCommitted()
            , 'sessionRefreshAdvice'  => $angularApp->getSessionRefreshAdvice()
            , 'navTo'                 => $angularApp->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
            ],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    });

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->delete('/{resourceType}/{resourceId}[/{ifcPath:.*}]', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];
        /** @var \Ampersand\AngularApp $angularApp */
        $angularApp = $this['angular_app'];

        // Input
        $pathList = ResourcePath::makePathList($args['ifcPath']);

        // Prepare
        $transaction = $ampersandApp->newTransaction();
        $entry = ResourceList::makeWithoutInterface($args['resourceType'])->one($args['resourceId']);
        
        // Perform delete
        $entry->walkPathToResource($pathList)->delete();
        
        // Close transaction
        $transaction->runExecEngine()->close();
        if ($transaction->isCommitted()) {
            $ampersandApp->userLog()->notice("Resource deleted");
        }
        
        $ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
        
        // Return result
        return $response->withJson(
            [ 'notifications'         => $ampersandApp->userLog()->getAll()
            , 'invariantRulesHold'    => $transaction->invariantRulesHold()
            , 'isCommitted'           => $transaction->isCommitted()
            , 'sessionRefreshAdvice'  => $angularApp->getSessionRefreshAdvice()
            , 'navTo'                 => $angularApp->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
            ],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    });
})->add($middleWare1);
