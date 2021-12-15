<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs;

use Ampersand\Core\Atom;
use Ampersand\Core\Link;
use Ampersand\Core\Relation;
use Ampersand\Core\SrcOrTgt;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
interface RelationPlugInterface extends StorageInterface
{
    /**
    * Check if link exists in storage
    */
    public function linkExists(Link $link): bool;
    
    /**
    * Get all links given a relation
    *
    * If src and/or tgt atom is specified only links are returned with these atoms
    * @return \Ampersand\Core\Link[]
    */
    public function getAllLinks(Relation $relation, ?Atom $srcAtom = null, ?Atom $tgtAtom = null): array;
    
    /**
     * Add link (srcAtom,tgtAtom) in storage
     */
    public function addLink(Link $link): void;
    
    /**
     * Delete link (srcAtom,tgtAtom) from storage
     */
    public function deleteLink(Link $link): void;
    
    /**
     * Delete all links in the specified relation with the specified atom as src or target
     */
    public function deleteAllLinks(Relation $relation, Atom $atom, SrcOrTgt $srcOrTgt): void;

    /**
     * Delete all links in a relation
     */
    public function emptyRelation(Relation $relation): void;
}
