<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs;

use Ampersand\Core\Atom;
use Ampersand\Core\Link;
use Ampersand\Core\Relation;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
interface RelationPlugInterface extends StorageInterface
{
    
    public function linkExists(Link $link);
    
    /**
    * Get all links given a relation
    *
    * If src and/or tgt atom is specified only links are returned with these atoms
    * @return Link[]
    */
    public function getAllLinks(Relation $relation, ?Atom $srcAtom = null, ?Atom $tgtAtom = null): array;
    
    public function addLink(Link $link): void;
    
    public function deleteLink(Link $link): void;
    
    /**
     * Delete all links in the specified relation with the specified atom as src or target
     */
    public function deleteAllLinks(Relation $relation, Atom $atom, string $srcOrTgt): void;

    /**
     * Delete all links in a relation
     */
    public function emptyRelation(Relation $relation): void;
}
