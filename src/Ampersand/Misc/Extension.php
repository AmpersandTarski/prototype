<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Extension
{
    protected $name;

    protected $bootstrapFile;

    /**
     * Constructor
     */
    public function __construct(string $name, ?string $bootstrapFile = null)
    {
        $this->name = $name;
        $this->bootstrapFile = $bootstrapFile;
    }

    /**
     * Bootstrap the extensions (i.e. load php bootstrap file if specified)
     */
    public function bootstrap(): self
    {
        if (!is_null($this->bootstrapFile)) {
            require_once($this->bootstrapFile);
        }
        
        return $this;
    }
}
