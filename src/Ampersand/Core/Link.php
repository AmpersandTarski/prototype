<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Core;

use Exception;
use JsonSerializable;
use Ampersand\Core\Atom;
use Ampersand\Core\Relation;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Link implements JsonSerializable
{
    /**
     * Relation of which this link is a instance
     */
    protected Relation $rel;
    
    /**
     * Source atom of this link
     */
    protected Atom $src;
    
    /**
     * Target atom of this link
     */
    protected Atom $tgt;
    
    public function __construct(Relation $rel, Atom $src, Atom $tgt)
    {
        $this->rel = $rel;
        $this->src = $src;
        $this->tgt = $tgt;
        
        // Checks
        if (!in_array($this->src->concept, $this->rel->srcConcept->getSpecializationsIncl())) {
            throw new Exception("Cannot instantiate link {$this}, because source atom does not match relation source concept or any of its specializations", 500);
        }
        if (!in_array($this->tgt->concept, $this->rel->tgtConcept->getSpecializationsIncl())) {
            throw new Exception("Cannot instantiate link {$this}, because target atom does not match relation target concept or any of its specializations", 500);
        }
    }
    
    /**
     * Function is called when object is treated as a string
     */
    public function __toString(): string
    {
        return "({$this->src},{$this->tgt}) {$this->rel}";
    }
    
    /**
     * Function is called when object encoded to json with json_encode()
     */
    public function jsonSerialize(): array
    {
        return ['src' => $this->src, 'tgt' => $this->tgt];
    }
    
    /**
     * Check if link exists in relation
     */
    public function exists(): bool
    {
        return $this->rel->linkExists($this);
    }
    
    /**
     * Add link relation set
     */
    public function add(): self
    {
        $this->rel->addLink($this);
        return $this;
    }
    
    /**
     * Delete link from relation set
     */
    public function delete(): self
    {
        $this->rel->deleteLink($this);
        return $this;
    }
    
    /**
     * Return relation
     */
    public function relation(): Relation
    {
        return $this->rel;
    }
    
    /**
     * Return source atom
     */
    public function src(): Atom
    {
        return $this->src;
    }
    
    /**
     * Return target atom
     */
    public function tgt(): Atom
    {
        return $this->tgt;
    }
}
