<?php

namespace Ampersand\Controller;

use Ampersand\AmpersandApp;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Frontend\FrontendInterface;
use Ampersand\Log\Logger;
use Ampersand\Misc\ServiceKey;
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

    /**
     * Refuse the request when the application runs in production mode.
     *
     * A machine that carries the configured service key in the X-Ampersand-Service-Key header
     * passes this gate; see {@see \Ampersand\Misc\ServiceKey}. Every endpoint that calls this
     * method is covered, so a deployment pipeline reaches the installer and the exporter while
     * the application stays closed for everyone else.
     *
     * The refusal message names no reason: a caller must not be able to tell a wrong key from a
     * key that was never configured.
     */
    protected function preventProductionMode(): void
    {
        if (ServiceKey::productionGuardApplies($this->app->getSettings(), $_SERVER, Logger::getLogger('APPLICATION'))) {
            throw new AccessDeniedException("Not allowed in production environment");
        }
    }
}
