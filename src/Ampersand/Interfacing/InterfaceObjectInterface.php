<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Relation;
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

    public function isLeaf(int $options): bool;
    public function isIdent(): bool;
    public function isUni(): bool;
    public function isTot(): bool;
    public function isEditable(): bool;

    public function getPath(): string;

    public function crudC(): bool;
    public function crudR(): bool;
    public function crudU(): bool;
    public function crudD(): bool;

    /**********************************************************************************************
     * METHODS to walk through interface
     *********************************************************************************************/
    /**
     * Returns specific target atom
     *
     * @param \Ampersand\Core\Atom $src
     * @param string $tgtId
     * @return \Ampersand\Core\Atom
     */
    public function one(Atom $src, string $tgtId): Atom;

    /**
     * Returns list of target atoms
     *
     * @param \Ampersand\Core\Atom $src
     * @return \Ampersand\Core\Atom[]
     */
    public function all(Atom $src): array;

    /**********************************************************************************************
     * Sub interface objects METHODS
     *********************************************************************************************/
    /**
     * Return list of sub interface objects
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getSubinterfaces(): array;
    public function hasSubinterface(string $ifcId): bool;
    public function getSubinterface(string $ifcId): InterfaceObjectInterface;
    public function getSubinterfaceByLabel(string $ifcLabel): InterfaceObjectInterface;

    /**********************************************************************************************
     * CRUD METHODS
     *********************************************************************************************/
    public function create(Atom $src, $tgtId = null): Atom;
    public function read(Atom $src, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = []);
    public function set(Atom $src, $value = null): bool;
    public function add(Atom $src, $value): bool;
    public function remove(Atom $src, $value): bool;
    public function removeAll(Atom $src): bool;
    public function delete(Atom $tgtAtom): bool;

    /**********************************************************************************************
     * HELPER METHODS
     *********************************************************************************************/
    
    /**
     * Return list of all sub interface objects recursively (incl. the current object itself)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getIfcObjFlattened();

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
