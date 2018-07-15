<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use stdClass;
use Exception;
use ArrayIterator;
use IteratorAggregate;
use Ampersand\Core\Atom;
use Ampersand\Log\Logger;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\Resource;
use Ampersand\Interfacing\InterfaceExprObject;
use Ampersand\Interfacing\InterfaceObjectInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ResourceList
{
    
    /**
    *
    * @var \Psr\Log\LoggerInterface
    */
    protected $logger;
    
    /**
     * The source Resource (i.e. Atom) of this resource list
     *
     * @var \Ampersand\Interfacing\Resource
     */
    protected $src = null;
    
    /**
     * The Interface that is the base of this resource list
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface
     */
    protected $ifc = null;
    
    /**
     * List with target resources
     *
     * @var \Ampersand\Interfacing\Resource[]
     */
    protected $tgts = null;
    
    /**
     * Constructor
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @param \Ampersand\Interfacing\InterfaceExprObject $ifc
     * @param bool $skipAccessCheck
     */
    public function __construct(Resource $src, InterfaceExprObject $ifc, bool $skipAccessCheck = false)
    {
        /** @var \Pimple\Container $container */
        global $container; // TODO: remove dependency on global $container var
        $this->logger = Logger::getLogger('INTERFACING');
        
        if ($ifc->isRoot() && !$container['ampersand_app']->isAccessibleIfc($ifc) && !$skipAccessCheck) {
            throw new Exception("Unauthorized to access interface {$ifc}", 403);
        }
        
        // Epsilon. TODO: remove after multiple concept specifications are possible for Atom objects
        if ($src->concept !== $ifc->srcConcept) {
            $this->src = clone $src;
            $this->src->concept = $ifc->srcConcept;
        // No epsilon
        } else {
            $this->src = $src;
        }
        
        $this->ifc = $ifc;
    }
    
    /**
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public function getIfc(): InterfaceObjectInterface
    {
        return $this->ifc;
    }

    /**
     * Resource factory. Instantiates a new target resource
     *
     * @param string $tgtId
     * @return \Ampersand\Interfacing\Resource
     */
    protected function makeResource(string $tgtId): Resource
    {
        return new Resource($tgtId, $this->ifc->tgtConcept, $this->ifc, $this->src);
    }

    /**
     * Resource factory. Instantiates a new target resource with a new (random) id
     *
     * @return \Ampersand\Interfacing\Resource
     */
    protected function makeNewResource(): Resource
    {
        $cpt = $this->ifc->tgtConcept;
        return new Resource($cpt->createNewAtomId(), $cpt, $this->ifc, $this->src);
    }

/**************************************************************************************************
 * Methods to call on ResourceList
 *************************************************************************************************/
    
    /**
     * @param \stdClass $resourceToPost
     * @return \Ampersand\Interfacing\Resource
     */
    public function post(stdClass $resourceToPost): Resource
    {
        if (!$this->ifc->crudC()) {
            throw new Exception("Create not allowed for ". $this->ifc->getPath(), 405);
        }
        
        // Use attribute '_id_' if provided
        if (isset($resourceToPost->_id_)) {
            $resource = $this->makeResource($resourceToPost->_id_);
            if ($resource->exists()) {
                throw new Exception("Cannot create resource that already exists", 400);
            }
        } elseif ($this->ifc->isIdent()) {
            $resource = $this->makeResource($this->src->id);
        } else {
            $resource = $this->makeNewResource();
        }
        
        // If interface is editable, also add tuple(src, tgt) in interface relation
        if ($this->ifc->isEditable()) {
            $this->add($resource->id, true);
        } else {
            $resource->add();
        }
        
        // Put resource attributes
        $resource->put($resourceToPost);
        
        return $resource;
    }
    
    /**
     * Alias of set() method. Used by Resource::patch() method
     * @param mixed|null $value
     * @return bool
     */
    public function replace($value = null): bool
    {
        if (!$this->ifc->isUni()) {
            throw new Exception("Cannot use replace for non-univalent interface " . $this->ifc->getPath() . ". Use add or remove instead", 400);
        }
        return $this->set($value);
    }
    
    /**
     * Set provided value (for univalent interfaces)
     *
     * @param mixed|null $value
     * @return bool
     */
    public function set($value = null): bool
    {
        if (!$this->ifc->isUni()) {
            throw new Exception("Cannot use set() for non-univalent interface " . $this->ifc->getPath() . ". Use add or remove instead", 400);
        }
        
        // Handle Ampersand properties [PROP]
        if ($this->ifc->isProp()) {
            if ($value === true) {
                $this->add($this->src->id);
            } elseif ($value === false) {
                $this->remove($this->src->id);
            } else {
                throw new Exception("Boolean expected, non-boolean provided.", 400);
            }
        } else {
            if (is_null($value)) {
                $this->removeAll();
            } else {
                $this->add($value);
            }
        }
        
        return true;
    }
    
    /**
     * Add value to resource list
     * @param mixed $value
     * @param bool $skipCrudUCheck
     * @return bool
     */
    public function add($value, bool $skipCrudUCheck = false): bool
    {
        if (!isset($value)) {
            throw new Exception("Cannot add item. Value not provided", 400);
        }
        if (is_object($value) || is_array($value)) {
            throw new Exception("Literal expected but " . gettype($value) . " provided while updating " . $this->ifc->getPath(), 400);
        }
        
        if (!$this->ifc->isEditable()) {
            throw new Exception("Interface is not editable " . $this->ifc->getPath(), 405);
        }
        if (!$this->ifc->crudU() && !$skipCrudUCheck) {
            throw new Exception("Update not allowed for " . $this->ifc->getPath(), 405);
        }
        
        $tgt = new Atom($value, $this->ifc->tgtConcept);
        if ($tgt->concept->isObject() && !$this->ifc->crudC() && !$tgt->exists()) {
            throw new Exception("Create not allowed for " . $this->ifc->getPath(), 405);
        }
        
        $tgt->add();
        $this->src->link($tgt, $this->ifc->relation(), $this->ifc->relationIsFlipped)->add();
        
        return true;
    }
    
    /**
     * Remove value from resource list
     *
     * @param mixed $value
     * @return bool
     */
    public function remove($value): bool
    {
        if (!isset($value)) {
            throw new Exception("Cannot remove item. Value not provided", 400);
        }
        if (is_object($value) || is_array($value)) {
            throw new Exception("Literal expected but " . gettype($value) . " provided while updating " . $this->ifc->getPath(), 400);
        }
        
        if (!$this->ifc->isEditable()) {
            throw new Exception("Interface is not editable " . $this->ifc->getPath(), 405);
        }
        if (!$this->ifc->crudU()) {
            throw new Exception("Update not allowed for " . $this->ifc->getPath(), 405);
        }
        
        $tgt = new Atom($value, $this->ifc->tgtConcept);
        $this->src->link($tgt, $this->ifc->relation(), $this->ifc->relationIsFlipped)->delete();
        
        return true;
    }
    
    /**
     * Undocumented function
     *
     * @return bool
     */
    public function removeAll(): bool
    {
        if (!$this->ifc->isEditable()) {
            throw new Exception("Interface is not editable " . $this->ifc->getPath(), 405);
        }
        if (!$this->ifc->crudU()) {
            throw new Exception("Update not allowed for " . $this->ifc->getPath(), 405);
        }
        
        foreach ($this->getTgtResources() as $tgt) {
            $this->src->link($tgt, $this->ifc->relation(), $this->ifc->relationIsFlipped)->delete();
        }

        return true;
    }
}
