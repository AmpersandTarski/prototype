<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Concept;
use Ampersand\Interfacing\InterfaceObjectFactory;
use Ampersand\Interfacing\Resource;
use Exception;
use Ampersand\Core\Atom;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ResourceFactory
{
    /**
     * Return all resources for a given resourceType
     * TODO: refactor when resources (e.g. for update field in UI) can be requested with interface definition
     * @param string $resourceType name/id of concept
     * @return \Ampersand\Interfacing\Resource[]
     */
    public static function getAllResources($resourceType)
    {
        $concept = Concept::getConcept($resourceType);
        
        if (!$concept->isObject()) {
            throw new Exception("Cannot get resource(s) given non-object concept {$concept}.", 500);
        }
        
        $resources = [];
        foreach ($concept->getAllAtomObjects() as $atom) {
            $r = new Resource($atom->id, $concept, InterfaceObjectFactory::getNullObject(), null);
            $r->setQueryData($atom->getQueryData());
            $resources[] = $r->get();
        }
        
        return $resources;
    }

    /**
     * Factory function for Resource class
     *
     * @param string $id
     * @param string $conceptName
     * @return \Ampersand\Interfacing\Resource
     */
    public static function makeResource(string $id, string $conceptName): Resource
    {
        return new Resource($id, Concept::getConcept($conceptName), InterfaceObjectFactory::getNullObject(), null);
    }

    /**
     * Factory function for new resource object
     *
     * @param string $conceptName
     * @return \Ampersand\Interfacing\Resource
     */
    public static function makeNewResource(string $conceptName): Resource
    {
        try {
            $concept = Concept::getConcept($conceptName);
        } catch (Exception $e) {
            throw new Exception("Resource type not found", 404);
        }
        
        if (!$concept->isObject() || $concept->isSession()) {
            throw new Exception("Resource type not found", 404); // Prevent users to instantiate resources of scalar type or SESSION
        }
        
        return new Resource($concept->createNewAtomId(), $concept, InterfaceObjectFactory::getNullObject(), null);
    }

    /**
     * Factory function to create a Resource object using an Atom object
     *
     * @param \Ampersand\Core\Atom $atom
     * @return \Ampersand\Interfacing\Resource
     */
    public static function makeResourceFromAtom(Atom $atom): Resource
    {
        return new Resource($atom->id, $atom->concept, InterfaceObjectFactory::getNullObject(), null);
    }
}
