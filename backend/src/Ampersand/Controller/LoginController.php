<?php

namespace Ampersand\Controller;

use Ampersand\Exception\AmpersandException;
use Ampersand\Exception\BadRequestException;
use Slim\Http\Request;
use Slim\Http\Response;

class LoginController extends AbstractController
{
    public function loginTest(Request $request, Response $response, array $args): Response
    {
        $this->preventProductionMode();

        if (!$this->app->getSettings()->get('session.loginEnabled')) {
            throw new AmpersandException("Testing login feature not applicable. Login functionality is not enabled");
        }

        if (!isset($args['accountId'])) {
            throw new BadRequestException("No account identifier 'accountId' provided");
        }

        $account = $this->app->getModel()->getConcept('Account')->makeAtom($args['accountId']);

        $this->app->login($account);

        return $this->success($response);
    }
}