<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Ampersand\Interfacing\ViewSegment;
use Exception;
use Ampersand\Core\Atom;
use Ampersand\Rule\Rule;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ViolationSegment extends ViewSegment
{

    /**
     * The view to which this segment belongs to
     */
    protected Rule $rule;

    /**
     * Specifies if expression is the ident relation (in case of an Expr segment type)
     */
    protected ?bool $expIsIdent = null;

    /**
     * Specifies if violation segment concerns a SRC or TGT atom. NULL if N/A
     */
    protected ?string $srcOrTgt = null;

    /**
     * Constructor of violation segments as part of specified rule
     */
    public function __construct(array $segmentDef, Rule $rule)
    {
        $this->rule = $rule;
        $this->expIsIdent = $segmentDef['expIsIdent'];

        // From ViewSegment class
        $this->seqNr = $segmentDef['seqNr'];
        $this->label = $segmentDef['seqNr'];
        $this->segType = $segmentDef['segType'];
        $this->text = $segmentDef['text'];
        $this->expSQL = $segmentDef['expSQL'];
        $this->srcOrTgt = $segmentDef['srcOrTgt'];
        
        if (!($this->segType === 'Text' || $this->segType === 'Exp')) {
            throw new Exception("Unsupported segmentType '{$this->segType}' in RULE segment '{$this}'", 501); // 501: Not implemented
        }
    }
    
    public function __toString(): string
    {
        return $this->rule . ":{$this->label}";
    }
    
    /**
     * Undocumented function
     *
     * Tgt atom parameter is declared optional, because the method must be compatible with the parent method it overwrites (i.e. ViewSegment::getData())
     */
    public function getData(Atom $srcAtom, ?Atom $tgtAtom = null): array
    {
        if (is_null($tgtAtom)) {
            throw new Exception("No target atom provided for ViolationSegment::getData()", 500);
        }

        switch ($this->segType) {
            case "Text":
                return [$this->text];
                break;
            case "Exp":
                // select starting atom depending on whether the segment uses the src of tgt atom.
                if (is_null($this->srcOrTgt)) {
                    throw new Exception("Cannot evaluate segment expression without SRC or TGT defined", 500);
                }
                $atom = $this->srcOrTgt === 'Src' ? $srcAtom : $tgtAtom;
                if ($this->expIsIdent) {
                    // when segment expression isIdent (i.e. SRC I or TGT I), we don't have to evaluate the expression.
                    return [$atom->getId()];
                } else {
                    return $this->rule->getPlug()->executeViewExpression($this, $atom);
                }
                break;
            default:
                throw new Exception("Unsupported segmentType '{$this->segType}' in RULE segment '{$this}'", 501); // 501: Not implemented
                break;
        }
    }
}
