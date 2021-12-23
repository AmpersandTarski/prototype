<?php

namespace Ampersand\Controller;

use Ampersand\Core\Concept;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\ConceptNotDefined;
use Ampersand\Misc\ProtoContext;
use Slim\Http\Request;
use Slim\Http\Response;

class ResourceController extends AbstractController
{
    public function renameAtoms(Request $request, Response $response, array $args): Response
    {
        if ($this->app->getSettings()->get('global.productionEnv')) {
            throw new AccessDeniedException("Not allowed in production environment", 403);
        }
        
        $resourceType = $args['resourceType'];
        if (!$this->app->getModel()->getConcept($resourceType)->isObject()) {
            throw new ConceptNotDefined("Resource type not found", 404);
        }
        
        $list = $request->reparseBody()->getParsedBody();
        if (!is_array($list)) {
            throw new BadRequestException("Body must be array. Non-array provided", 400);
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
                throw new BadRequestException("Regenerate atom ids is not allowed for scalar concept types (alphanumeric, decimal, date, ..)", 400);
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
