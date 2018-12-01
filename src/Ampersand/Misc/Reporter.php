<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Exception;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\IO\AbstractWriter;
use Ampersand\Rule\Conjunct;
use Ampersand\Core\Relation;
use Ampersand\Interfacing\Ifc;

class Reporter
{

    /**
     * Writer
     *
     * @var \Ampersand\IO\AbstractWriter
     */
    protected $writer;

    public function __construct(AbstractWriter $writer)
    {
        $this->writer = $writer;
    }

    /**
     * Write relation definition report
     * Specifies multiplicity constraints, related conjuncts and other
     * aspects of all relations
     *
     * @return \Ampersand\Misc\Reporter
     */
    public function reportRelationDefinitions(): Reporter
    {
        $content = array_map(function (Relation $relation) {
            $relArr = [];
            
            $relArr['signature'] = $relation->signature;
            
            // Get multiplicity constraints
            $constraints = [];
            if ($relation->isUni) {
                $constraints[] = "[UNI]";
            }
            if ($relation->isTot) {
                $constraints[] = "[TOT]";
            }
            if ($relation->isInj) {
                $constraints[] = "[INJ]";
            }
            if ($relation->isSur) {
                $constraints[] = "[SUR]";
            }
            $relArr['constraints'] = empty($constraints) ? "no constraints" : implode(',', $constraints);
            
            $relArr['affectedConjuncts'] = [];
            foreach ($relation->getRelatedConjuncts() as $conjunct) {
                $relArr['affectedConjuncts'][$conjunct->id] = [];
                foreach ($conjunct->invRuleNames as $ruleName) {
                    $relArr['affectedConjuncts'][$conjunct->id]['invRules'][] = $ruleName;
                }
                foreach ($conjunct->sigRuleNames as $ruleName) {
                    $relArr['affectedConjuncts'][$conjunct->id]['sigRules'][] = $ruleName;
                }
            }
            $relArr['srcOrTgtTable'] = $relation->getMysqlTable()->tableOf;
            
            return $relArr;
        }, Relation::getAllRelations());

        $this->writer->write($content);
        
        return $this;
    }

    /**
     * Write interface report
     * Specifies aspects for all interfaces (incl. subinterfaces), like path, label,
     * crud-rights, etc
     *
     * @return \Ampersand\Misc\Reporter
     */
    public function reportInterfaceDefinitions(): Reporter
    {
        $content = [];
        foreach (Ifc::getAllInterfaces() as $key => $ifc) {
            /** @var \Ampersand\Interfacing\Ifc $ifc */
            $content = array_merge($content, $ifc->getIfcObject()->getInterfaceFlattened());
        }
        
        $content = array_map(function (InterfaceObjectInterface $ifcObj) {
            return $ifcObj->getTechDetails();
        }, $content);

        $this->writer->write($content);

        return $this;
    }

    /**
     * Write interface issue report
     * Currently focussed on CRUD rights
     *
     * @return \Ampersand\Misc\Reporter
     */
    public function reportInterfaceIssues(): Reporter
    {
        $content = [];
        foreach (Ifc::getAllInterfaces() as $key => $interface) {
            /** @var \Ampersand\Interfacing\Ifc $interface */
            foreach ($interface->getIfcObject()->getInterfaceFlattened() as $ifcObj) {
                /** @var InterfaceObjectInterface $ifcObj */
                $content = array_merge($content, $ifcObj->diagnostics());
            }
        }

        if (empty($content)) {
            $content[] = ['No issues found'];
        }

        $this->writer->write($content);
        
        return $this;
    }

    /**
     * Write conjunct usage report
     * Specifies which conjuncts are used by which rules, grouped by invariants,
     * signals, and unused conjuncts
     *
     * @return \Ampersand\Misc\Reporter
     */
    public function reportConjunctUsage(): Reporter
    {
        $content = [];
        foreach (Conjunct::getAllConjuncts() as $conj) {
            if ($conj->isInvConj()) {
                $content['invConjuncts'][] = $conj->__toString();
            }
            if ($conj->isSigConj()) {
                $content['sigConjuncts'][] = $conj->__toString();
            }
            if (!$conj->isInvConj() && !$conj->isSigConj()) {
                $content['unused'][] = $conj->__toString();
            }
        }

        $this->writer->write($content);

        return $this;
    }

    /**
     * Write conjunct performance report
     *
     * @param \Ampersand\Rule\Conjunct[] $conjuncts
     * @return \Ampersand\Misc\Reporter
     */
    public function reportConjunctPerformance(array $conjuncts): Reporter
    {
        $content = [];
        
        // run all conjuncts (from - to)
        foreach ($conjuncts as $conjunct) {
            /** @var \Ampersand\Rule\Conjunct $conjunct */
            $startTimeStamp = microtime(true); // true means get as float instead of string
            $conjunct->evaluate();
            $endTimeStamp = microtime(true);
            set_time_limit((int) ini_get('max_execution_time')); // reset time limit counter
            
            $content = [ 'id' => $conjunct->id
                       , 'start' => round($startTimeStamp, 6)
                       , 'end' => round($endTimeStamp, 6)
                       , 'duration' => round($endTimeStamp - $startTimeStamp, 6)
                       , 'invariantRules' => implode(';', $conjunct->invRuleNames)
                       , 'signalRules' => implode(';', $conjunct->sigRuleNames)
                       ];
        }
        
        usort($content, function ($a, $b) {
            return $b['duration'] <=> $a['duration']; // uses php7 spaceship operator
        });

        $this->writer->write($content);

        return $this;
    }
}
