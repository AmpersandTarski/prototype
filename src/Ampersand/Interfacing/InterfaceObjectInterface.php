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
     * Returns specific target atom as Resource object
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @param string $tgtId
     * @return \Ampersand\Interfacing\Resource
     */
    public function one(Resource $src, string $tgtId): Resource;

    /**
     * Returns list of target atoms
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @return \Ampersand\Interfacing\Resource[]
     */
    public function all(Resource $src): array;

    /**
     * Returns path for given tgt resource
     *
     * @param \Ampersand\Interfacing\Resource $tgt
     * @param \Ampersand\Interfacing\Resource|null $parent
     * @return string
     */
    public function buildResourcePath(Resource $tgt, Resource $parent = null): string;

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
    public function create(Resource $src, $tgtId = null): Resource;
    public function read(Resource $src, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = []);
    public function set(Atom $src, $value = null): bool;
    public function add(Atom $src, $value): bool;
    public function remove(Atom $src, $value): bool;
    public function removeAll(Atom $src): bool;
    public function delete(Resource $tgtAtom): bool;

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
