<?php

namespace Ampersand\Controller;

use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\AmpersandException;
use Ampersand\Exception\AtomNotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;

class LoginController extends AbstractController
{
    public function loginTest(Request $request, Response $response, array $args): Response
    {
        $this->preventProductionMode();

        if (!$this->app->getSettings()->get('session.loginEnabled')) {
            throw new AmpersandException("Testing login feature not applicable. Login functionality is not enabled", 400);
        }

        if (!isset($args['accountId'])) {
            throw new AtomNotFoundException("No account identifier 'accountId' provided", 400);
        }

        $account = $this->app->getModel()->getConceptByLabel('Account')->makeAtom($args['accountId']);

        $this->app->login($account);

        return $this->success($response);
    }
}