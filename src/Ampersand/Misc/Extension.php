<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\Misc\Settings;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Extension
{
    protected $name;

    protected $bootstrapFile;

    protected $configFile;

    /**
     * Undocumented function
     *
     * @param string $name
     * @param string|null $bootstrapFile
     * @param string|null $configFile
     */
    public function __construct(string $name, string $bootstrapFile = null, string $configFile = null)
    {
        $this->name = $name;
        $this->bootstrapFile = $bootstrapFile;
        $this->configFile = $configFile;
    }

    /**
     * Bootstrap the extensions (i.e. load php bootstrap file if specified)
     *
     * @return \Ampersand\Misc\Extension $this
     */
    public function bootstrap(): Extension
    {
        if (!is_null($this->bootstrapFile)) {
            require_once($this->bootstrapFile);
        }
        
        return $this;
    }
}
