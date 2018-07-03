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

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceTxtObject extends InterfaceObject
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
     * @param bool $rootIfc Specifies if this interface object is a toplevel interface (true) or subinterface (false)
     */
    protected function __construct(array $ifcDef, IfcPlugInterface $plug, string $pathEntry = null, bool $rootIfc = false)
    {
        if ($ifcDef['type'] != 'ObjText') {
            throw new Exception("Provided interface definition is not of type ObjText", 500);
        }

        $this->plug = $plug;
        $this->isRoot = $rootIfc;
        
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
    public function __toString()
    {
        return $this->id;
    }
    
    /**
     * Returns interface relation (when interface expression = relation), throws exception otherwise
     * @return \Ampersand\Core\Relation|\Exception
     */
    public function relation()
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
    }
    
    /**
     * Returns if interface expression is editable (i.e. expression = relation)
     * @return boolean
     */
    public function isEditable()
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
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
     * Returns if interface expression relation is a property
     * @return boolean
     */
    public function isProp()
    {
        return false;
    }
    
    /**
     * Returns if interface is a reference to another interface
     * @return boolean
     */
    public function isRef()
    {
        return false;
    }
    
    /**
     * Returns identifier of interface object to which this interface refers to (or null if not set)
     * @return string|null
     */
    public function getRefToIfcId()
    {
        return null;
    }
    
    /**
     * Returns referenced interface object
     * @throws Exception when $this is not a reference interface
     * @return InterfaceObject
     */
    public function getRefToIfc()
    {
        throw new Exception("Interface is not a reference interface: " . $this->getPath(), 500);
    }
    
    /**
     * Returns if interface is a LINKTO reference to another interface
     * @return boolean
     */
    public function isLinkTo()
    {
        return false;
    }
    
    /**
     * Returns if interface object is a top level interface
     * @return boolean
     */
    public function isRoot()
    {
        return $this->isRoot;
    }
    
    /**
     * Returns if interface object is a leaf node
     * @return boolean
     */
    public function isLeaf()
    {
        return true;
    }
    
    /**
     * Returns if interface is a public interface (i.e. accessible every role, incl. no role)
     * @return boolean
     */
    public function isPublic()
    {
        return empty($this->ifcRoleNames) && $this->isRoot();
    }
    
    /**
     * Returns if the interface expression isIdent
     * Note! Epsilons are not included
     *
     * @return boolean
     */
    public function isIdent(): bool
    {
        return false;
    }
    
    public function isUni()
    {
        return true;
    }
    
    public function isTot()
    {
        return true;
    }
    
    public function getPath()
    {
        return $this->path;
    }
    
    public function getView()
    {
        return null;
    }

    public function getBoxClass()
    {
        return $this->boxClass;
    }
    
    public function crudC()
    {
        return false;
    }
    
    public function crudR()
    {
        return true;
    }
    
    public function crudU()
    {
        return false;
    }
    public function crudD()
    {
        return false;
    }

    /**
     * Returns generated query for this interface expression
     * @return string
     */
    public function getQuery()
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
    }

    /**
     * Returns parent interface object (or null if not applicable)
     *
     * @return \Ampersand\Interfacing\InterfaceObject|null
     */
    public function getParentInterface()
    {
        return $this->parentIfc;
    }
    
    /**
     * @param string $ifcId
     * @return InterfaceObject
     */
    public function getSubinterface($ifcId)
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
    }
    
    /**
     * @param string $ifcLabel
     * @return InterfaceObject
     */
    public function getSubinterfaceByLabel($ifcLabel)
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
    }
    
    /**
     * Return array with all sub interface recursively (incl. the interface itself)
     * @return InterfaceTxtObject[]
     */
    public function getInterfaceFlattened()
    {
        return [$this];
    }
    
    /**
     * @param int $options
     * @return InterfaceObject[]
     */
    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS)
    {
        return [];
    }
    
    /**
     *
     * @return InterfaceObject[]
     */
    public function getNavInterfacesForTgt()
    {
        return [];
    }
    
    /**
     * Returns interface data (tgt atoms) for given src atom
     * @param \Ampersand\Core\Atom $srcAtom atom to take as source atom for this interface expression query
     * @return array
     */
    public function getIfcData(Atom $srcAtom): array
    {
        throw new Exception("N.a. for InterfaceTxtObject", 500);
    }

    /**
     * Returns interface data for a given src atom
     *
     * @param \Ampersand\Core\Atom $srcAtom
     * @return mixed
     */
    public function getIfcData2(Atom $srcAtom)
    {
        return $this->txt;
    }
}
