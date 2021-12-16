<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs;

use Ampersand\Core\Atom;
use Ampersand\Core\Concept;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
interface ConceptPlugInterface extends StorageInterface
{
    /**
    * Check if atom exists in storage
    */
    public function atomExists(Atom $atom): bool;
    
    /**
     * Get all atoms for given concept
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getAllAtoms(Concept $concept): array;
    
    /**
     * Add atom to storage
     */
    public function addAtom(Atom $atom): void;
    
    /**
     * Remove an atom as member from a concept set
     */
    public function removeAtom(Atom $atom): void;
    
    /**
     * Delete atom from storage
     */
    public function deleteAtom(Atom $atom): void;
}
