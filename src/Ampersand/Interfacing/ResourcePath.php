<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Interfacing\Resource;
use Exception;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ResourcePath
{
    /**
     * Source atom of this resource path
     *
     * @var \Ampersand\Interfacing\Resource
     */
    protected $src;

    /**
     * Undocumented variable
     *
     * @var string[]
     */
    protected $pathList;

    /**
     * Last atom of this resource path (can be the same as $src)
     *
     * @var \Ampersand\Interfacing\Resource
     */
    protected $tgt;

    /**
     * A trailing interface object (or null if path ends with an atom)
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface|null
     */
    protected $trailingIfc = null;

    /**
     * Constructor
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @param string|array $path
     */
    public function __construct(Resource $src, $path)
    {
        $this->src = $src;

        // Prepare path list
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        $path = trim($path, '/'); // remove root slash (e.g. '/Projects/xyz/..') and trailing slash (e.g. '../Projects/xyz/')
        if ($path === '') {
            $this->pathList = []; // support no path
        } else {
            $this->pathList = explode('/', $path);
        }

        $this->tgt = $this->walkPath($src, $this->pathList);
    }

    public function __toString(): string
    {
        return $this->src . '/' . implode('/', $this->pathList);
    }

    /**
     * Returns the last atom of this path
     *
     * @return \Ampersand\Interfacing\Resource
     */
    public function getTgt(): Resource
    {
        return $this->tgt;
    }

    /**
     * Returns the trailing interface (or null if this path ends with an atom)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface|null
     */
    public function getTrailingIfc()
    {
        return $this->trailingIfc;
    }

    /**
     * Returns if this path end with an interface identifier
     *
     * @return boolean
     */
    public function hasTrailingIfc(): bool
    {
        return !is_null($this->getTrailingIfc());
    }

    protected function walkPath(Resource $src, array $pathList): Resource
    {
        // Try to create atom ($this) if not exists (yet)
        if (!$src->exists()) {
            // Automatically create if allowed
            // if ($this->ifc->crudC()) {
                // $this->add();
            // } else {
            throw new Exception("Resource '{$this}' not found", 404);
            // }
        }

        $resource = $src;
        while (count($pathList)) {
            // Peel off first part of path (= ifc identifier)
            $subifc = $resource->getIfc()->getSubinterface(array_shift($pathList), Options::INCLUDE_REF_IFCS | Options::INCLUDE_LINKTO_IFCS);

            // If the subifc isIdent, step into next resource.
            if ($subifc->isIdent()) {
                $resource = $subifc->one($resource, $resource->id);
            // Elseif there is at one more part of the path
            } elseif (count($pathList)) {
                $resource = $subifc->one($resource, array_shift($pathList));
            // Else, this path ends with an ifc
            } else {
                $this->trailingIfc = $subifc;
            }
        }

        return $resource;
    }
}
