<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Exception;
use Ampersand\Core\Atom;
use Ampersand\Plugs\IfcPlugInterface;
use Ampersand\Interfacing\Options;
use Ampersand\Core\Relation;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Resource;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceTxtObject extends InterfaceExprObject
{
    /**
     * The string that is the content of this interface object
     *
     * @var string
     */
    private $txt;

    /**
     * Constructor
     *
     * @param array $ifcDef Interface object definition as provided by Ampersand generator
     * @param \Ampersand\Plugs\IfcPlugInterface $plug
     * @param string|null $pathEntry
     */
    public function __construct(array $ifcDef, IfcPlugInterface $plug, string $pathEntry = null)
    {
        if ($ifcDef['type'] != 'ObjText') {
            throw new Exception("Provided interface definition is not of type ObjText", 500);
        }

        $this->plug = $plug;
        
        // Set attributes from $ifcDef
        $this->id = $ifcDef['id'];
        $this->label = $ifcDef['label'];
        $this->path = is_null($pathEntry) ? $this->label : "{$pathEntry}/{$this->label}"; // Use label, because path is only used for human readable purposes (e.g. Exception messages)
        $this->txt = $ifcDef['txt'];
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
     * Returns interface relation (when interface expression = relation), throws exception otherwise
     * @throws \Exception when interface expression is not an (editable) relation
     * @return \Ampersand\Core\Relation
     */
    public function relation(): Relation
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
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
    
    /**
     * Return list of all sub interface objects recursively (incl. the current object itself)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getIfcObjFlattened()
    {
        return [$this];
    }

    public function read(Resource $src, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = [])
    {
        return $this->txt;
    }

    public function delete(Resource $tgtAtom): bool
    {
        throw new Exception("Detele operation not implemented for TXT interface object", 501);
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
}
