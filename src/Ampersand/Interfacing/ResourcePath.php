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
class ResourcePath
{
    /**
     * Undocumented variable
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
     * Undocumented variable
     *
     * @var boolean
     */
    protected $isEvaluated = false;

    /**
     * Undocumented variable
     *
     * @var \Ampersand\Interfacing\Resource|null
     */
    protected $tgt = null;

    /**
     * Undocumented variable
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
    }

    public function __toString(): string
    {
        return implode('/', $this->pathList);
    }

    /**
     * Returns the target (last Resource) of this path
     *
     * @return \Ampersand\Interfacing\Resource
     */
    public function getTgt(): Resource
    {
        if (!$this->isEvaluated) {
            $this->walkPath($this->src, $this->pathList);
        }

        return $this->tgt;
    }

    /**
     * Returns the trailing interface (or null if this path ends with a Resource)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface|null
     */
    public function getTrailingIfc()
    {
        if (!$this->isEvaluated) {
            $this->walkPath($this->src, $this->pathList);
        }

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

    protected function walkPath(Resource $src, $pathList): Resource
    {
        // Try to create resource ($this) if not exists (yet)
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
            $subifc = $resource->getIfc()->getSubinterface(array_shift($pathList));

            // If the subifc isIdent, step into next Resource.
            // See explaination in Resource::setPath() method why this elseif construct is here
            if ($subifc->isIdent()) {
                $resource = $subifc->one($resource);
            // Elseif there is at one more part of the path
            } elseif (count($pathList)) {
                $resource = $subifc->one($resource, array_shift($pathList));
            // Else, this path end with an ifc
            } else {
                $this->trailingIfc = $subifc;
            }
        }

        return $this->tgt = $resource;
    }
}
