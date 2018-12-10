<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Interfacing\InterfaceObjectFactory;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Plugs\IfcPlugInterface;
use Exception;
use Ampersand\Core\Concept;

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
    protected $isAPI;

    /**
     * Roles that have access to this interface.
     * Empty list implies public interface (i.e. for everyone)
     *
     * @var string[]
     */
    protected $ifcRoleNames = [];

    /**
     * Root interface object (must be a InterfaceExprObject)
     *
     * @var \Ampersand\Interfacing\InterfaceExprObject
     */
    protected $ifcObject;

    /**
     * Constructor
     *
     * @param string $id
     * @param string $label
     * @param bool $isAPI
     * @param array $ifcRoleNames
     * @param array $objectDef
     * @param \Ampersand\Plugs\IfcPlugInterface $defaultPlug
     */
    public function __construct(string $id, string $label, bool $isAPI, array $ifcRoleNames, array $objectDef, IfcPlugInterface $defaultPlug)
    {
        $this->id = $id;
        $this->label = $label;
        $this->isAPI = $isAPI;
        $this->ifcRoleNames = $ifcRoleNames;
        $this->ifcObject = InterfaceObjectFactory::newExprObject($objectDef, $defaultPlug);
    }

    public function __toString(): string
    {
        return $this->id;
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isPublic(): bool
    {
        return empty($this->ifcRoleNames);
    }

    public function getIfcObject(): InterfaceObjectInterface
    {
        return $this->ifcObject;
    }

    public function getSrcConcept(): Concept
    {
        return $this->ifcObject->srcConcept;
    }

    public function getTgtConcept(): Concept
    {
        return $this->ifcObject->tgtConcept;
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
     * @return \Ampersand\Interfacing\Ifc
     */
    public static function getInterface(string $ifcId): Ifc
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
     * @return \Ampersand\Interfacing\Ifc
     */
    public static function getInterfaceByLabel(string $ifcLabel): Ifc
    {
        foreach (self::getAllInterfaces() as $interface) {
            /** @var \Ampersand\Interfacing\Ifc $interface */
            if ($interface->getLabel() === $ifcLabel) {
                return $interface;
            }
        }
        
        throw new Exception("Interface with label '{$ifcLabel}' is not defined", 500);
    }
    
    /**
     * Returns all interfaces
     *
     * @return \Ampersand\Interfacing\Ifc[]
     */
    public static function getAllInterfaces(): array
    {
        if (!isset(self::$allInterfaces)) {
            throw new Exception("Interface definitions not loaded yet", 500);
        }
        
        return self::$allInterfaces;
    }
    
    /**
     * Returns all interfaces that are public (i.e. not assigned to a role)
     *
     * @return \Ampersand\Interfacing\Ifc[]
     */
    public static function getPublicInterfaces(): array
    {
        return array_values(array_filter(self::getAllInterfaces(), function (Ifc $ifc) {
            return $ifc->isPublic();
        }));
    }

    public static function getInterfacesForConcept(Concept $cpt): array
    {
        return array_values(array_filter(self::getAllInterfaces(), function (Ifc $ifc) use ($cpt) {
            return $ifc->getSrcConcept()->hasSpecialization($cpt, true);
        }));
    }
    
    /**
     * Import all interface object definitions from json file and instantiate interfaces
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
