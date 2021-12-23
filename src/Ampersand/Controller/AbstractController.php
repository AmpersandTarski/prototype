<?php

namespace Ampersand\Controller;

use Ampersand\AmpersandApp;
use Ampersand\AngularApp;
use Psr\Container\ContainerInterface;

abstract class AbstractController
{
    protected ContainerInterface $container;

    protected AmpersandApp $app;

    protected AngularApp $angularApp;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->app = $this->container->get('ampersand_app');
        $this->angularApp = $this->container->get('angular_app');
    }
}
