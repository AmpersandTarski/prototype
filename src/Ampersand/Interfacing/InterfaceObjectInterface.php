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

    public function relation(): Relation;
    public function isEditable(): bool;
    public function getEditableConcepts();
    public function isProp(): bool;

    public function isRef(): bool;
    public function getRefToIfcId();
    public function getRefToIfc();

    public function isLinkTo(): bool;
    public function isRoot(): bool;
    public function isLeaf(): bool;
    public function isPublic(): bool;

    public function isIdent(): bool;
    public function isUni(): bool;
    public function isTot(): bool;

    public function getPath(): string;
    public function getBoxClass();

    public function crudC(): bool;
    public function crudR(): bool;
    public function crudU(): bool;
    public function crudD(): bool;

    public function getQuery(): string;

    public function getParentInterface();
    public function getSubinterface(string $ifcId): InterfaceObjectInterface;
    public function getSubinterfaceByLabel(string $ifcLabel): InterfaceObjectInterface;
    public function getInterfaceFlattened();
    public function getSubinterfaces(int $options);
    public function getNavInterfacesForTgt();

    public function getIfcData(Atom $srcAtom): array;
    public function getIfcData2(Atom $srcAtom);
    public function getViewData(Atom $tgtAtom): array;

    public function getTechDetails(): array;

    public function put(Resource $tgtAtom, $value): bool;
    public function delete(Resource $tgtAtom): bool;
}
