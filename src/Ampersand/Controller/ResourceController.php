<?php

namespace Ampersand\Controller;

use Ampersand\Core\Concept;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\NotDefined\ConceptNotDefined;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\MethodNotAllowedException;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\ResourceList;
use Ampersand\Interfacing\ResourcePath;
use Ampersand\Misc\ProtoContext;
use Slim\Http\Request;
use Slim\Http\Response;

class ResourceController extends AbstractController
{
    public function listResourceTypes(Request $request, Response $response, array $args): Response
    {
        $this->requireAdminRole();
        
        $content = array_values(
            array_map(function ($cpt) {
                return $cpt->label; // only show label of resource types
            }, array_filter($this->app->getModel()->getAllConcepts(), function ($cpt) {
                return $cpt->isObject(); // filter concepts without a representation (i.e. resource types)
            }))
        );
        
        return $response->withJson($content, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getAllResourcesForType(Request $request, Response $response, array $args): Response
    {
        // TODO: refactor when resources (e.g. for update field in UI) can be requested with interface definition
        $resources = ResourceList::makeWithoutInterface($this->app->getModel()->getConcept($args['resourceType']));
        
        return $response->withJson(
            $resources->get(),
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function createNewResourceId(Request $request, Response $response, array $args): Response
    {
        $resource = ResourceList::makeWithoutInterface($this->app->getModel()->getConcept($args['resourceType']))->post();
        
        // Don't save/commit new resource (yet)
        // Transaction is not closed

        return $response->withJson(
            ['_id_' => $resource->getId()],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function getResource(Request $request, Response $response, array $args): Response
    {
        // Input
        $pathList = ResourcePath::makePathList($args['resourcePath']);
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');

        // Prepare
        $resource = ResourceList::makeWithoutInterface($this->app->getModel()->getConcept($args['resourceType']))->one($args['resourceId']);

        // Output
        return $response->withJson(
            $resource->walkPath($pathList)->get($options, $depth),
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function putPatchPostResource(Request $request, Response $response, array $args): Response
    {
        // Input
        $pathList = ResourcePath::makePathList($args['ifcPath']);
        $options = Options::getFromRequestParams($request->getQueryParams());
        $depth = $request->getQueryParam('depth');
        $body = $request->reparseBody()->getParsedBody();

        // Prepare
        $transaction = $this->app->newTransaction();
        $entry = ResourceList::makeWithoutInterface($this->app->getModel()->getConcept($args['resourceType']))->one($args['resourceId']);

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
                throw new FatalException("Unsupported HTTP method");
        }

        // Run ExecEngine
        $transaction->runExecEngine();

        // Get content to return
        try {
            $content = $resource->get($options, $depth);
        } catch (AccessDeniedException | MethodNotAllowedException $e) {
            $content = $request->getMethod() === 'PATCH' ? null : $body;
        }
        
        // Close transaction
        $transaction->close();
        if ($transaction->isCommitted()) {
            $this->app->userLog()->notice($successMessage);
        }
        $this->app->checkProcessRules(); // Check all process rules that are relevant for the activate roles

        // Output
        return $response->withJson(
            [ 'content'               => $content
            , 'patches'               => $request->getMethod() === 'PATCH' ? $body : []
            , 'notifications'         => $this->app->userLog()->getAll()
            , 'invariantRulesHold'    => $transaction->invariantRulesHold()
            , 'isCommitted'           => $transaction->isCommitted()
            , 'sessionRefreshAdvice'  => $this->frontend->getSessionRefreshAdvice()
            , 'navTo'                 => $this->frontend->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
            ],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function deleteResource(Request $request, Response $response, array $args): Response
    {
        // Input
        $pathList = ResourcePath::makePathList($args['ifcPath']);

        // Prepare
        $transaction = $this->app->newTransaction();
        $entry = ResourceList::makeWithoutInterface($this->app->getModel()->getConcept($args['resourceType']))->one($args['resourceId']);
        
        // Perform delete
        $entry->walkPathToResource($pathList)->delete();
        
        // Close transaction
        $transaction->runExecEngine()->close();
        if ($transaction->isCommitted()) {
            $this->app->userLog()->notice("Resource deleted");
        }
        
        $this->app->checkProcessRules(); // Check all process rules that are relevant for the activate roles
        
        // Return result
        return $response->withJson(
            [ 'notifications'         => $this->app->userLog()->getAll()
            , 'invariantRulesHold'    => $transaction->invariantRulesHold()
            , 'isCommitted'           => $transaction->isCommitted()
            , 'sessionRefreshAdvice'  => $this->frontend->getSessionRefreshAdvice()
            , 'navTo'                 => $this->frontend->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
            ],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function renameAtoms(Request $request, Response $response, array $args): Response
    {
        $this->requireAdminRole();
        
        $resourceType = $args['resourceType'];
        if (!$this->app->getModel()->getConcept($resourceType)->isObject()) {
            throw new ConceptNotDefined("Resource type '{$resourceType}' not found");
        }
        
        $list = $request->reparseBody()->getParsedBody();
        if (!is_array($list)) {
            throw new BadRequestException("Body must be array. Non-array provided");
        }

        $transaction = $this->app->newTransaction();

        foreach ($list as $item) {
            $atom = $this->app->getModel()->getConceptByLabel($resourceType)->makeAtom($item->oldId);
            $atom->rename($item->newId);
        }
        
        $transaction->runExecEngine()->close();

        return $response->withJson(
            $list,
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function regenerateAtomIds(Request $request, Response $response, array $args): Response
    {
        $this->requireAdminRole();
        
        // Input
        $cptName = isset($args['concept']) ? $args['concept'] : null;
        $prefix = $request->getQueryParam('prefix');
        $prefixWithConceptName = is_null($prefix) ? null : filter_var($prefix, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // Determine which concepts to regenerate atom ids
        if (!is_null($cptName)) {
            $cpt = $this->app->getModel()->getConcept($cptName);
            if (!$cpt->isObject()) {
                throw new BadRequestException("Regenerate atom ids is not allowed for scalar concept types (alphanumeric, decimal, date, ..)");
            }
            $conceptList = [$cpt];
        } else {
            $conceptList = array_filter($this->app->getModel()->getAllConcepts(), function (Concept $concept) {
                return $concept->isObject() // we only regenerate object identifiers, not scalar concepts because that wouldn't make sense
                    && !ProtoContext::containsConcept($concept) // filter out concepts from ProtoContext, otherwise interfaces and RBAC doesn't work anymore
                    && !($concept->isSession() || $concept->isONE())// filter out the concepts SESSION and ONE
                    && $concept->isRoot(); // specializations are automatically handled, therefore we only take root concepts (top of classification tree)
            });
        }

        // Do the work
        $transaction = $this->app->newTransaction();
        
        foreach ($conceptList as $concept) {
            $concept->regenerateAllAtomIds($prefixWithConceptName);
        }
        
        // Close transaction
        $transaction->runExecEngine()->close();
        $transaction->isCommitted() ? $this->app->userLog()->notice("Run completed") : $this->app->userLog()->warning("Run completed but transaction not committed");
        
        return $this->success($response);
    }
}
