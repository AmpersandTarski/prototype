<?php

namespace Ampersand\Controller;

use Ampersand\AmpersandApp;
use Psr\Container\ContainerInterface;

abstract class AbstractController
{
    protected ContainerInterface $container;

    protected AmpersandApp $app;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->app = $this->container->get('ampersand_app');
    }
}
