<?php

namespace Ampersand\Controller;

use Ampersand\AmpersandApp;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Frontend\FrontendInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

abstract class AbstractController
{
    protected ContainerInterface $container;

    protected AmpersandApp $app;

    protected FrontendInterface $frontend;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->app = $this->container->get('ampersand_app');
        $this->frontend = $this->app->frontend();
    }

    protected function success(Response $response): Response
    {
        // Check all process rules that are relevant for the activate roles
        $this->app->checkProcessRules();

        return $response->withJson(
            $this->app->userLog()->getAll(), // Return all notifications
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    protected function requireAdminRole(): void
    {
        // Access check
        if (!$this->app->hasRole($this->app->getSettings()->get('rbac.adminRoles'))) {
            throw new AccessDeniedException("You do not have admin role access");
        }
    }

    protected function preventProductionMode(): void
    {
        if ($this->app->getSettings()->get('global.productionEnv')) {
            throw new AccessDeniedException("Not allowed in production environment");
        }
    }
}
