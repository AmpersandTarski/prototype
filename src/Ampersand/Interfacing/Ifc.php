<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Ifc
{
    /**
     * Interface id (i.e. escaped name) to use for referencing
     *
     * @var string
     */
    protected $id;

    /**
     * Human readable name of the interface (i.e. name as specified in Ampersand script)
     *
     * @var string
     */
    protected $label;

    /**
     * Specifies if this Interface is intended as API
     *
     * @var bool
     */
    protected $isApi;

    /**
     * Roles that have access to this interface
     *
     * @var string[]
     */
    protected $ifcRoleNames = [];

    /**
     * Root interface object
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface
     */
    protected $ifcObject;

    /**
     * Constructor
     */
    public function __construct()
    {
        
    }
}
