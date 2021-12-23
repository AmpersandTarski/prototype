<?php

namespace Ampersand\Controller;

use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\ConceptNotDefined;
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
}
