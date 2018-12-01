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
     * Contains all interface definitions
     *
     * @var \Ampersand\Interfacing\Ifc[]
     */
    protected static $allInterfaces;

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

    /**
     * Returns identifier of this interface
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**********************************************************************************************
     * STATIC METHODS
    **********************************************************************************************/
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
            $ifc = new Ifc($ifcDef['id'], $ifcDef['label'], $ifcDef['isAPI'], $ifcDef['interfaceRoles'], $ifcDef['ifcObject'], $defaultPlug);
            self::$allInterfaces[$ifc->getId()] = $ifc;
        }
    }
}
