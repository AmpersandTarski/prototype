<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Ampersand\Core\Atom;
use Ampersand\Rule\Rule;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Violation
{

    /**
     * Rule to which this violation belongs to
     */
    protected Rule $rule;

    /**
     * The source atom of the violation
     */
    protected Atom $src;

    /**
     * The target atom of the violation
     */
    protected Atom $tgt;

    /**
     * The violation message
     */
    protected string $message;

    /**
     * Constructor of violation
     */
    public function __construct(Rule $rule, string $srcAtomId, string $tgtAtomId)
    {
        $this->rule = $rule;
        $this->src = new Atom($srcAtomId, $rule->srcConcept);
        $this->tgt = new Atom($tgtAtomId, $rule->tgtConcept);
    }
    
    /**
     * Function is called when object is treated as a string
     */
    public function __toString(): string
    {
        return "({$this->src},{$this->tgt})";
    }

    public function getRule(): Rule
    {
        return $this->rule;
    }

    public function getSrc(): Atom
    {
        return $this->src;
    }

    public function getTgt(): Atom
    {
        return $this->tgt;
    }
    
    /**
     * Get violation message
     */
    public function getViolationMessage(): string
    {
        $strArr = [];
        foreach ($this->rule->getViolationSegments() as $segment) {
            $tgtAtomIds = $segment->getData($this->src, $this->tgt);
            $strArr[] = count($tgtAtomIds) ? implode(', ', $tgtAtomIds) : null;
        }

        // If empty array of strings (i.e. no violation segments defined), use default violation representation: '<src>,<tgt>'
        return $this->message = empty($strArr) ? "{$this->src},{$this->tgt}" : implode($strArr);
    }

    /**
     * Get violation message prepared for ExecEngine
     */
    public function getExecEngineViolationMessage(): string
    {
        $strArr = [];
        foreach ($this->rule->getViolationSegments() as $segment) {
            $tgtAtomIds = $segment->getData($this->src, $this->tgt);

            if (count($tgtAtomIds) == 0) {
                $strArr[] = '_NULL'; // use reserved keyword '_NULL' to specify in the return string that segment is empty (i.e. no target atom for expr)
            } else { // >= 1
                $str = implode('_AND', $tgtAtomIds); // use reserved keyword '_AND' as separator between multiple atom ids

                // Prevent certain user input that has special meaning in ExecEngine. Only allow when segment type is 'Text' (i.e. segment is specified in &-script)
                if ($segment->getType() != 'Text') {
                    $strArr[] = str_replace(['{EX}','{php}'], '', $str);
                } else {
                    $strArr[] = $str;
                }
            }
        }
        return $this->message = implode($strArr); // glue as one string
    }
}
