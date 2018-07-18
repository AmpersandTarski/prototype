<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Model;

use Exception;
use Ampersand\Interfacing\InterfaceExprObject;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\InterfaceNullObject;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceObjectFactory
{
    /**
     * Contains all interface definitions
     * @var \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    private static $allInterfaces; // contains all interface objects
    
    public static function getNullObject(): InterfaceObjectInterface
    {
        static $ifc = null;

        return $ifc ?? $ifc = new InterfaceNullObject();
    }

    /**
     * Returns if interface exists
     * @var string $ifcId Identifier of interface
     * @return bool
     */
    public static function interfaceExists(string $ifcId): bool
    {
        return array_key_exists($ifcId, self::getAllInterfaces());
    }
    
    /**
     * Returns toplevel interface object
     * @param string $ifcId
     * @throws \Exception when interface does not exist
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public static function getInterface(string $ifcId): InterfaceObjectInterface
    {
        if (!array_key_exists($ifcId, $interfaces = self::getAllInterfaces())) {
            throw new Exception("Interface '{$ifcId}' is not defined", 500);
        }

        return $interfaces[$ifcId];
    }
    
    /**
     * Undocumented function
     *
     * @param string $ifcLabel
     * @throws \Exception when interface does not exist
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public static function getInterfaceByLabel(string $ifcLabel): InterfaceObjectInterface
    {
        foreach (self::getAllInterfaces() as $interface) {
            if ($interface->getIfcLabel() == $ifcLabel) {
                return $interface;
            }
        }
        
        throw new Exception("Interface with label '{$ifcLabel}' is not defined", 500);
    }
    
    /**
     * Returns all toplevel interface objects
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public static function getAllInterfaces(): array
    {
        if (!isset(self::$allInterfaces)) {
            throw new Exception("Interface definitions not loaded yet", 500);
        }
        
        return self::$allInterfaces;
    }
    
    /**
     * Returns all toplevel interface objects that are public (i.e. not assigned to a role)
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public static function getPublicInterfaces(): array
    {
        return array_values(array_filter(self::getAllInterfaces(), function ($ifc) {
            return $ifc->isPublic();
        }));
    }
    
    /**
     * Import all interface object definitions from json file and instantiate InterfaceObjectInterface objects
     *
     * @param string $fileName containing the Ampersand interface definitions
     * @param \Ampersand\Plugs\IfcPlugInterface $defaultPlug
     * @return void
     */
    public static function setAllInterfaces(string $fileName, IfcPlugInterface $defaultPlug)
    {
        self::$allInterfaces = [];
        
        $allInterfaceDefs = (array)json_decode(file_get_contents($fileName), true);
        
        foreach ($allInterfaceDefs as $ifcDef) {
            $ifc = new InterfaceExprObject($ifcDef['ifcObject'], $defaultPlug, null, true);
            
            // Set additional information about this toplevel interface object
            $ifc->ifcRoleNames = $ifcDef['interfaceRoles'];
            
            self::$allInterfaces[$ifc->getIfcId()] = $ifc;
        }
    }
}
