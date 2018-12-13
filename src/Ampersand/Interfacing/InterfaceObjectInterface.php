<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Resource;
use Ampersand\Interfacing\Options;
use Ampersand\Core\Concept;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
interface InterfaceObjectInterface
{
    public function __toString(): string;
    public function getIfcId(): string;
    public function getIfcLabel(): string;

    public function getEditableConcepts();
    public function getTargetConcept(): Concept;
    public function isIdent(): bool;
    public function isUni(): bool;

    public function getPath(): string;

    public function crudC(): bool;
    public function crudR(): bool;
    public function crudU(): bool;
    public function crudD(): bool;

    /**********************************************************************************************
     * METHODS to walk through interface
     *********************************************************************************************/

    /**
     * Returns list of target atoms
     *
     * @param \Ampersand\Core\Atom $src
     * @return \Ampersand\Core\Atom[]
     */
    public function getTgtAtoms(Atom $src, string $selectTgt = null): array;

    /**
     * Returns path for given tgt atom
     *
     * @param \Ampersand\Core\Atom $tgt
     * @param string $pathToSrc
     * @return string
     */
    public function buildResourcePath(Atom $tgt, string $pathToSrc): string;

    /**********************************************************************************************
     * Sub interface objects METHODS
     *********************************************************************************************/
    /**
     * Return list of sub interface objects
     *
     * @param int $options
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS): array;
    public function hasSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): bool;
    public function getSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface;
    public function getSubinterfaceByLabel(string $ifcLabel, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface;

    /**********************************************************************************************
     * CRUD METHODS
     *********************************************************************************************/
    public function create(Atom $src, $tgtId = null): Atom;
    public function read(Atom $src, string $pathToSrc, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = []);
    public function set(Atom $src, $value = null): ?Atom;
    public function add(Atom $src, $value): Atom;
    public function remove(Atom $src, $value): void;
    public function removeAll(Atom $src): void;
    public function delete(Resource $tgtAtom): void;

    /**********************************************************************************************
     * HELPER METHODS
     *********************************************************************************************/
    
    /**
     * Return list of all sub interface objects recursively (incl. the current object itself)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getIfcObjFlattened(): array;

    /**
     * Return properties of interface object
     *
     * @return array
     */
    public function getTechDetails(): array;

    /**
     * Return diagnostic information of interface object
     *
     * @return array
     */
    public function diagnostics(): array;
}
