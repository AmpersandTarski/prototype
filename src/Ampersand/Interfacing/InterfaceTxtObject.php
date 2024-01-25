<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Resource;
use Ampersand\Core\Atom;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\MetaModelException;
use Ampersand\Interfacing\AbstractIfcObject;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceTxtObject extends AbstractIfcObject implements InterfaceObjectInterface
{
    /**
     * Interface id (i.e. safe name) to use in framework
     */
    protected string $id;

    /**
     * Interface name to show in UI
     */
    protected string $label;

    /**
     * The string that is the content of this interface object
     */
    protected string $txt;

    /**
     * Path to this interface object (for debugging purposes)
     */
    protected string $path;

    /**
     * Constructor
     */
    public function __construct(array $ifcDef, ?InterfaceObjectInterface $parent = null)
    {
        if ($ifcDef['type'] != 'ObjText') {
            throw new FatalException("Provided interface definition is not of type ObjText");
        }
        
        // Set attributes from $ifcDef
        $this->id = $ifcDef['name'];
        $this->label = $ifcDef['label'];
        $this->txt = $ifcDef['txt'];

        // Use label for path, because this is only used for human readable purposes (e.g. Exception messages)
        $this->path = is_null($parent) ? $this->label : "{$parent->getPath()}/{$this->label}";
    }
    
    /**
     * Function is called when object is treated as a string
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
     * Array with all editable concepts for this interface and all sub interfaces
     * @return \Ampersand\Core\Concept[]
     */
    public function getEditableConcepts(): array
    {
        return [];
    }
    
    /**
     * Returns if the interface expression isIdent
     *
     * Note! Epsilons are not included
     */
    public function isIdent(): bool
    {
        return false;
    }
    
    public function isUni(): bool
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
    
    public function getTgtAtoms(Atom $src, string $selectTgt = null): array
    {
        throw new FatalException("Method getTgtAtoms() is n.a. for InterfaceTxtObject and must not be called");
    }

    /**
     * Returns path for given tgt atom
     */
    public function buildResourcePath(Atom $tgt, string $pathToSrc): string
    {
        throw new FatalException("Method buildResourcePath() is n.a. for InterfaceTxtObject and must not be called");
    }

    /**********************************************************************************************
     * Sub interface objects METHODS
     *********************************************************************************************/

    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS): array
    {
        return [];
    }

    public function hasSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): bool
    {
        return false;
    }
    
    public function getSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
    {
        throw new FatalException("Method getSubinterface() is n.a. for InterfaceTxtObject and must not be called");
    }
    
    public function getSubinterfaceByLabel(string $ifcLabel, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
    {
        throw new FatalException("Method getSubinterfaceByLabel() is n.a. for InterfaceTxtObject and must not be called");
    }

    /**********************************************************************************************
     * CRUD METHODS
     *********************************************************************************************/
    public function getViewData(Atom $tgtAtom): array
    {
        return [$this->txt];
    }

    public function create(Atom $src, $tgtId = null): Atom
    {
        throw new MetaModelException("Create operation not implemented for TXT interface object");
    }
    
    public function read(
        Atom $src,
        string $pathToSrc,
        string $tgtId = null,
        int $options = Options::DEFAULT_OPTIONS,
        int $depth = null,
        array $recursionArr = []
    ): string
    {
        return $this->txt;
    }

    public function set(Atom $src, $value = null): ?Atom
    {
        throw new BadRequestException("Set operation not implemented for fixed txt interface object");
    }

    public function add(Atom $src, $value): Atom
    {
        throw new BadRequestException("Add operation not implemented for fixed txt interface object");
    }

    public function remove(Atom $src, $value): void
    {
        throw new BadRequestException("Remove operation not implemented for fixed txt interface object");
    }

    public function removeAll(Atom $src): void
    {
        throw new BadRequestException("Remove operation not implemented for fixed txt interface object");
    }

    public function delete(Resource $tgtAtom): void
    {
        throw new BadRequestException("Detele operation not implemented for fixed txt interface object");
    }

    /**********************************************************************************************
     * HELPER METHODS
     *********************************************************************************************/

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
