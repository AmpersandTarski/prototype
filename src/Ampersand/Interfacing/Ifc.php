<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Plugs\IfcPlugInterface;
use Exception;
use Ampersand\Core\Concept;
use Ampersand\Model;
use Ampersand\Interfacing\InterfaceNullObject;
use Ampersand\Interfacing\InterfaceExprObject;
use Ampersand\Interfacing\InterfaceTxtObject;
use Ampersand\AmpersandApp;
use Ampersand\Core\Atom;
use Ampersand\Misc\ProtoContext;

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
    protected $isAPI;

    /**
     * Root interface object (must be a InterfaceExprObject)
     *
     * @var \Ampersand\Interfacing\InterfaceExprObject
     */
    protected $ifcObject;

    /**
     * Reference to Ampersand model
     *
     * @var \Ampersand\Model
     */
    protected $model;

    /**
     * Constructor
     *
     * @param string $id
     * @param string $label
     * @param bool $isAPI
     * @param array $objectDef
     * @param \Ampersand\Plugs\IfcPlugInterface $defaultPlug
     * @param \Ampersand\Model $model
     */
    public function __construct(string $id, string $label, bool $isAPI, array $objectDef, IfcPlugInterface $defaultPlug, Model $model)
    {
        $this->id = $id;
        $this->label = $label;
        $this->isAPI = $isAPI;
        $this->model = $model;
        $this->ifcObject = $this->newExprObject($objectDef, $defaultPlug);
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

    /**
     * Atom representation of this interface object
     *
     * @return \Ampersand\Core\Atom
     */
    public function getIfcAtom(): Atom
    {
        return $this->model->getInterfaceConcept()->makeAtom($this->id);
    }

    public function isPublic(): bool
    {
        return !empty($this->getIfcAtom()->getTargetAtoms(ProtoContext::REL_IFC_IS_PUBLIC, false));
    }

    public function isAPI(): bool
    {
        return $this->isAPI;
    }

    public function getIfcObject(): InterfaceObjectInterface
    {
        return $this->ifcObject;
    }

    public function getSrcConcept(): Concept
    {
        return $this->ifcObject->getSrcConcept();
    }

    public function getTgtConcept(): Concept
    {
        return $this->ifcObject->getTgtConcept();
    }

    /**
     * List of roles that have access to this interface
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getRoleNames()
    {
        return $this->getIfcAtom()->getTargetAtoms(ProtoContext::REL_IFC_ROLES, false);
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    /**********************************************************************************************
     * FACTORY METHODS FOR INTERFACE OBJECTS
    **********************************************************************************************/

    public function newObject(array $objectDef, IfcPlugInterface $defaultPlug, InterfaceObjectInterface $parent = null): InterfaceObjectInterface
    {
        switch ($objectDef['type']) {
            case 'ObjExpression':
                return new InterfaceExprObject($objectDef, $defaultPlug, $this, $parent);
                break;
            case 'ObjText':
                return new InterfaceTxtObject($objectDef, $parent);
                break;
            default:
                throw new Exception("Unsupported/unknown InterfaceObject type specified: '{$objectDef['type']}' is not supported", 500);
                break;
        }
    }

    public function newExprObject(array $objectDef, IfcPlugInterface $defaultPlug, InterfaceObjectInterface $parent = null): InterfaceExprObject
    {
        if ($objectDef['type'] !== 'ObjExpression') {
            throw new Exception("Interface expression object definition required, but '{$objectDef['type']}' provided.", 500);
        }

        return new InterfaceExprObject($objectDef, $defaultPlug, $this, $parent);
    }
    
    public static function getNullObject(Concept $concept, AmpersandApp $app): InterfaceObjectInterface
    {
        return new InterfaceNullObject($concept, $app);
    }
}
