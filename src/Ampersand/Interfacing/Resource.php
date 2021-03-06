<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use stdClass;
use ArrayAccess;
use Exception;
use Ampersand\Core\Atom;
use Ampersand\Core\Concept;
use Ampersand\Log\Logger;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\ResourcePath;
use Ampersand\Interfacing\ResourceList;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Resource extends Atom implements ArrayAccess
{
    /**
     * Interface for this resource.
     * The interface defines which properties and methods the resource has.
     * Interface definitions are generated by the Ampersand prototype generator.
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface
     */
    protected $ifc;

    /**
     * Parent resource list
     *
     * @var \Ampersand\Interfacing\ResourceList
     */
    protected $parentList;
    
    /**
     * Constructor
     *
     * @param string $resourceId Ampersand atom identifier
     * @param \Ampersand\Core\Concept $cpt
     * @param \Ampersand\Interfacing\ResourceList $parentList
     */
    public function __construct(string $resourceId, Concept $cpt, ResourceList $parentList)
    {
        // if (!$cpt->isObject()) {
        //     throw new Exception("Cannot instantiate resource, because its type '{$cpt}' is a non-object concept", 400);
        // }
        
        // Call Atom constructor
        parent::__construct($resourceId, $cpt);

        $this->ifc = $parentList->getIfcObject(); // shortcut
        $this->parentList = $parentList;
    }

    /**
     * Function is called when object is treated as a string
     * This functionality is needed when the ArrayAccess::offsetGet method below is used by internal code
     *
     * @return string
     */
    public function __toString()
    {
        return (string) parent::jsonSerialize();
    }

    public function getLabel(): string
    {
        return implode('', $this->ifc->getViewData($this));
    }

    /**
     * Return interface for this resource
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public function getIfc(): InterfaceObjectInterface
    {
        return $this->ifc;
    }

    /**********************************************************************************************
     * Methods to navigate through list
     *********************************************************************************************/

    public function one(string $ifcId, string $tgtId): Resource
    {
        return $this->all($ifcId)->one($tgtId);
    }

    public function all(string $ifcId): ResourceList
    {
        return new ResourceList(
            $this,
            $this->ifc->getSubinterface($ifcId, Options::INCLUDE_REF_IFCS | Options::INCLUDE_LINKTO_IFCS),
            $this->parentList->getResourcePath($this)
        );
    }

    /**
     * Undocumented function
     *
     * @param array $pathList
     * @return \Ampersand\Interfacing\Resource|\Ampersand\Interfacing\ResourceList
     */
    public function walkPath(array $pathList)
    {
        if (empty($pathList)) {
            return $this;
        } else {
            return $this->all(array_shift($pathList))->walkPath($pathList);
        }
    }

    public function walkPathToResource(array $pathList): Resource
    {
        if (empty($pathList)) {
            return $this;
        } else {
            return $this->all(array_shift($pathList))->walkPathToResource($pathList);
        }
    }

    public function walkPathToList(array $pathList): ResourceList
    {
        if (empty($pathList)) {
            throw new Exception("Provided path MUST NOT end with a resource identifier", 400);
        } else {
            return $this->all(array_shift($pathList))->walkPathToList($pathList);
        }
    }

/**************************************************************************************************
 * ArrayAccess methods
 *************************************************************************************************/

    /**
     * Implementation of ArrayAccess::offsetExists
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        $tgts = $this->all($offset)->getResources();
        return !empty($tgts);
    }

    /**
     * Implementation of ArrayAccess::offsetGet
     *
     * @param string $offset
     * @return \Ampersand\Interfacing\Resource|\Ampersand\Interfacing\Resource[]|null
     */
    public function offsetGet($offset)
    {
        $list = $this->all($offset);
        $tgts = $list->getResources();

        if ($list->isUni()) {
            return empty($tgts) ? null : current($tgts);
        } else {
            return $tgts;
        }
    }

    public function offsetSet($offset, $value)
    {
        throw new Exception("Resource::offsetSet not yet implemented", 501);
        // $ifcObj = $this->ifc->getSubinterface($offset, Options::INCLUDE_REF_IFCS | Options::INCLUDE_LINKTO_IFCS);
        // $ifcObj->set($this, $value);
    }

    public function offsetUnset($offset)
    {
        throw new Exception("Resource::offsetSet not yet implemented", 501);
        // $ifcObj = $this->ifc->getSubinterface($offset, Options::INCLUDE_REF_IFCS | Options::INCLUDE_LINKTO_IFCS);
        // $ifcObj->set($this, null);
    }

/**************************************************************************************************
 * REST methods to call on Resource
 *************************************************************************************************/
 
    /**
     * Get resource data according to provided interface
     * @param int $options
     * @param int|null $depth
     * @return array|string
     */
    public function get(int $options = Options::DEFAULT_OPTIONS, int $depth = null)
    {
        return $this->parentList->getOne($this->id, $options, $depth);
    }
    
    /**
     * Update a resource (updates only first level of subinterfaces, for now)
     * @param \stdClass|null $resourceToPut
     * @return \Ampersand\Interfacing\Resource $this
     */
    public function put(stdClass $resourceToPut = null): Resource
    {
        if (!isset($resourceToPut)) {
            return $this; // nothing to do
        }

        // Perform PUT using the interface definition
        foreach ($resourceToPut as $ifcId => $value) {
            if (substr($ifcId, 0, 1) === '_' && substr($ifcId, -1) === '_') {
                continue; // skip special internal attributes
            }
            try {
                $list = $this->all($ifcId);
            } catch (Exception $e) {
                Logger::getLogger('INTERFACING')->warning("Unknown attribute '{$ifcId}' in PUT data");
                continue;
            }

            if ($list->isUni()) {
                if (is_null($value) || is_scalar($value)) { // null or scalar (i.e. int, float, string, bool)
                    $list->set($value);
                } elseif (is_object($value)) {
                    if (isset($value->_id_)) { // object with _id_ attribute
                        $list->set($value->_id_);
                    } else { // object to post
                        $list->post($value);
                    }
                } else {
                    throw new Exception("Wrong datatype provided: expecting null, scalar or object for '{$list->getIfcObject()->getPath()}'", 400);
                }
            } else { // expect value to be array
                if (!is_array($value)) {
                    throw new Exception("Wrong datatype provided: expecting array for {$list->getIfcObject()->getPath()}", 400);
                }
                
                // First empty existing list
                $list->removeAll();
                
                // Add provided values
                foreach ($value as $item) {
                    if (is_scalar($item)) { // scalar (i.e. int, float, string, bool)
                        $list->add($item);
                    } elseif (is_object($item)) {
                        if (isset($item->_id_)) { // object with _id_ attribute
                            $list->add($item->_id_);
                        } else { // object to post
                            $list->post($item);
                        }
                    } else {
                        throw new Exception("Wrong datatype provided: expecting scalar or object for '{$list->getIfcObject()->getPath()}'", 400);
                    }
                }
            }
        }
        
        // Clear query data
        $this->setQueryData(null);
        
        return $this;
    }
    
    /**
     * Patch this resource with provided patches
     * Use JSONPatch specification for $patches (see: http://jsonpatch.com/)
     *
     * @param array $patches
     * @return \Ampersand\Interfacing\Resource $this
     */
    public function patch(array $patches): Resource
    {
        foreach ($patches as $key => $patch) {
            if (!property_exists($patch, 'op')) {
                throw new Exception("No 'op' (i.e. operation) specfied for patch #{$key}", 400);
            }
            if (!property_exists($patch, 'path')) {
                throw new Exception("No 'path' specfied for patch #{$key}", 400);
            }

            $pathList = ResourcePath::makePathList($patch->path);
            
            try {
                // Process patch
                switch ($patch->op) {
                    case "replace":
                        if (!property_exists($patch, 'value')) {
                            throw new Exception("No 'value' specfied", 400);
                        }
                        $this->walkPathToList($pathList)->set($patch->value);
                        break;
                    case "add":
                        if (!property_exists($patch, 'value')) {
                            throw new Exception("No 'value' specfied", 400);
                        }
                        $this->walkPathToList($pathList)->add($patch->value);
                        break;
                    case "remove":
                        // Regular json patch remove operation, uses last part of 'path' attribuut as resource to remove from list
                        if (!property_exists($patch, 'value')) {
                            $this->walkPathToResource($pathList)->remove();
                        // Not part of official json path specification. Uses 'value' attribute that must be removed from list
                        } elseif (property_exists($patch, 'value')) {
                            $this->walkPathToList($pathList)->remove($patch->value);
                        }
                        break;
                    case "create":
                        if (!property_exists($patch, 'value')) {
                            throw new Exception("No 'value' specfied", 400);
                        }
                        $this->walkPathToList($pathList)->create($patch->value);
                        break;
                    default:
                        throw new Exception("Unknown patch operation '{$patch->op}'. Supported are: 'replace', 'add' and 'remove', 'create'", 400);
                }
            } catch (Exception $e) {
                if ($e->getCode() >= 400 && $e->getCode() < 500) {
                    // Add patch # to all bad request (4xx) errors
                    throw new Exception("Error in patch #{$key}: {$e->getMessage()}", $e->getCode(), $e);
                } else {
                    throw $e;
                }
            }
        }
        
        // Clear query data
        $this->setQueryData(null);
        
        return $this;
    }

    public function post($subIfcId, stdClass $resourceToPost = null): Resource
    {
        return $this->all($subIfcId)->post($resourceToPost);
    }
    
    /**
     * Delete this resource and remove as target atom from current interface
     * @return \Ampersand\Interfacing\Resource $this
     */
    public function delete(): Resource
    {
        // Special case for FileObject: get filepath before deleting the atom
        if ($this->concept->isFileObject()) {
            $filePaths = []; // filePath[FileObject*FilePath] is UNI, so we expect max 1 link
            foreach ($this->getLinks('filePath[FileObject*FilePath]') as $link) {
                $filePaths[] = $link->tgt()->getId();
            }

            // Perform DELETE using the interface definition
            $this->ifc->delete($this);

            // Special case for FileObject: delete files from file system
            foreach ($filePaths as $path) {
                $this->concept->getApp()->fileSystem()->delete($path);
            }
        } else {
            // Perform DELETE using the interface definition
            $this->ifc->delete($this);
        }
        
        return $this;
    }

    public function remove(): void
    {
        $this->parentList->remove($this->id);
    }
}
