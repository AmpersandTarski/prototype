<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Exception;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Resource;
use Ampersand\Core\Atom;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceTxtObject implements InterfaceObjectInterface
{
    /**
     * Interface id (i.e. safe name) to use in framework
     *
     * @var string
     */
    protected $id;

    /**
     * Interface name to show in UI
     *
     * @var string
     */
    protected $label;

    /**
     * The string that is the content of this interface object
     *
     * @var string
     */
    protected $txt;

    /**
     * Path to this interface object (for debugging purposes)
     *
     * @var string
     */
    protected $path;

    /**
     * Constructor
     *
     * @param array $ifcDef Interface object definition as provided by Ampersand generator
     * @param \Ampersand\Interfacing\InterfaceObjectInterface|null $parent
     */
    public function __construct(array $ifcDef, InterfaceObjectInterface $parent = null)
    {
        if ($ifcDef['type'] != 'ObjText') {
            throw new Exception("Provided interface definition is not of type ObjText", 500);
        }
        
        // Set attributes from $ifcDef
        $this->id = $ifcDef['id'];
        $this->label = $ifcDef['label'];
        $this->txt = $ifcDef['txt'];

        // Use label for path, because this is only used for human readable purposes (e.g. Exception messages)
        $this->path = is_null($parent) ? $this->label : "{$parent->getPath()}/{$this->label}";
    }
    
    /**
     * Function is called when object is treated as a string
     * @return string
     */
    public function __toString(): string
    {
        return $this->id;
    }

    public function getIfcId(): string
    {
        return $this->id;
    }

    public function getIfcLabel(): string
    {
        return $this->label;
    }
    
    /**
     * Returns if interface expression is editable (i.e. expression = relation)
     * @return bool
     */
    public function isEditable(): bool
    {
        return false;
    }
    
    /**
     * Array with all editable concepts for this interface and all sub interfaces
     * @var \Ampersand\Core\Concept[]
     */
    public function getEditableConcepts()
    {
        return [];
    }
    
    /**
     * Returns if interface object is a leaf node
     * @return bool
     */
    public function isLeaf(int $options = Options::DEFAULT_OPTIONS): bool
    {
        return true;
    }
    
    /**
     * Returns if the interface expression isIdent
     * Note! Epsilons are not included
     *
     * @return bool
     */
    public function isIdent(): bool
    {
        return false;
    }
    
    public function isUni(): bool
    {
        return true;
    }
    
    public function isTot(): bool
    {
        return true;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function crudC(): bool
    {
        return false;
    }
    
    public function crudR(): bool
    {
        return true;
    }
    
    public function crudU(): bool
    {
        return false;
    }
    public function crudD(): bool
    {
        return false;
    }

    /**********************************************************************************************
     * METHODS to walk through interface
     *********************************************************************************************/
    public function one(Resource $src, string $tgtId): Resource
    {
        throw new Exception("Method one() is n.a. for InterfaceTxtObject and must not be called", 500);
    }

    public function all(Resource $src): array
    {
        throw new Exception("Method all() is n.a. for InterfaceTxtObject and must not be called", 500);
    }
    
    public function buildResourcePath(Resource $tgt, Resource $parent = null): string
    {
        throw new Exception("Method buildResourcePath() is n.a. for InterfaceTxtObject and must not be called", 500);
    }

    /**********************************************************************************************
     * Sub interface objects METHODS
     *********************************************************************************************/

    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS): array
    {
        return [];
    }

    public function hasSubinterface(string $ifcId): bool
    {
        return false;
    }
    
    /**
     * @param string $ifcId
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public function getSubinterface(string $ifcId): InterfaceObjectInterface
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
    }
    
    /**
     * @param string $ifcLabel
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public function getSubinterfaceByLabel(string $ifcLabel): InterfaceObjectInterface
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
    }

    /**********************************************************************************************
     * CRUD METHODS
     *********************************************************************************************/
    public function create(Resource $src, $tgtId = null): Resource
    {
        throw new Exception("Create operation not implemented for TXT interface object", 501);
    }
    
    public function read(Resource $src, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = [])
    {
        return $this->txt;
    }

    public function set(Atom $src, $value = null): bool
    {
        throw new Exception("Set operation not implemented for TXT interface object", 501);
    }

    public function add(Atom $src, $value): bool
    {
        throw new Exception("Add operation not implemented for TXT interface object", 501);
    }

    public function remove(Atom $src, $value): bool
    {
        throw new Exception("Remove operation not implemented for TXT interface object", 501);
    }

    public function removeAll(Atom $src): bool
    {
        throw new Exception("Remove operation not implemented for TXT interface object", 501);
    }

    public function delete(Resource $tgtAtom): bool
    {
        throw new Exception("Detele operation not implemented for TXT interface object", 501);
    }

    /**********************************************************************************************
     * HELPER METHODS
     *********************************************************************************************/

    /**
     * Return list of all sub interface objects recursively (incl. the current object itself)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getIfcObjFlattened(): array
    {
        return [$this];
    }

    public function getTechDetails(): array
    {
        return
            [ 'path' => $this->getPath()
            , 'label' => $this->getIfcLabel()
            , 'crudR' => $this->crudR()
            , 'crudU' => $this->crudU()
            , 'crudD' => $this->crudD()
            , 'crudC' => $this->crudC()
            , 'src' => 'n.a.'
            , 'tgt' => 'n.a.'
            , 'view' => 'n.a.'
            , 'relation' => 'n.a.'
            , 'flipped' => 'n.a.'
            , 'ref' => 'n.a.'
            ];
    }

    public function diagnostics(): array
    {
        return [];
    }
}
