<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Core\Relation;
use Ampersand\Interfacing\Ifc;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class Reporter
{
    /**
     * Encoder
     *
     * @var \Symfony\Component\Serializer\Encoder\EncoderInterface
     */
    protected $encoder;

    /**
     * Stream to output the report(s) to
     *
     * @var \Psr\Http\Message\StreamInterface
     */
    protected $stream;

    /**
     * Constructor
     *
     * @param \Symfony\Component\Serializer\Encoder\EncoderInterface $encoder
     * @param \Psr\Http\Message\StreamInterface $stream
     */
    public function __construct(EncoderInterface $encoder, StreamInterface $stream)
    {
        $this->encoder = $encoder;
        $this->stream = $stream;
    }

    /**
     * Encode and write data to stream
     *
     * @param string $format encoding format (must be supported by $this->encoder)
     * @param mixed $data data to output
     * @return void
     */
    protected function write(string $format, $data)
    {
        $this->stream->write($this->encoder->encode($data, $format));
    }

    /**
     * Write relation definition report
     * Specifies multiplicity constraints, related conjuncts and other aspects of provided relations
     *
     * @param \Ampersand\Core\Relation[] $relations
     * @param string $format
     * @return \Ampersand\Misc\Reporter
     */
    public function reportRelationDefinitions(array $relations, string $format): Reporter
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
                $relArr['affectedConjuncts'][] = $conjunct->showInfo();
            }
            $relArr['srcOrTgtTable'] = $relation->getMysqlTable()->inTableOf();
            
            return $relArr;
        }, $relations);

        $this->write($format, $content);
        
        return $this;
    }

    public function reportInterfaceDefinitions(array $interfaces, string $format): Reporter
    {
        $content = array_map(function (Ifc $ifc) {
            $ifcDetails =
                [ 'id' => $ifc->getId()
                , 'label' => $ifc->getLabel()
                , 'isAPI' => $ifc->isAPI()
                , 'isPublic' => $ifc->isPublic()
                , 'src' => $ifc->getSrcConcept()
                , 'tgt' => $ifc->getTgtConcept()
                , 'create' => $ifc->getIfcObject()->crudC()
                , 'read' => $ifc->getIfcObject()->crudR()
                , 'update' => $ifc->getIfcObject()->crudU()
                , 'delete' => $ifc->getIfcObject()->crudD()
                ];
            foreach ($ifc->getRoleNames() as $roleAtom) {
                $ifcDetails[$roleAtom->getId()] = true;
            }
            return $ifcDetails;
        }, $interfaces);

        $this->write($format, array_values($content));

        return $this;
    }

    /**
     * Write interface report
     * Inlcuding interface (sub) objects aspects like path, label, crud-rights, etc
     *
     * @param string $format
     * @return \Ampersand\Misc\Reporter
     */
    public function reportInterfaceObjectDefinitions(array $interfaces, string $format): Reporter
    {
        $content = [];
        foreach ($interfaces as $ifc) {
            /** @var \Ampersand\Interfacing\Ifc $ifc */
            $content = array_merge($content, $ifc->getIfcObject()->getIfcObjFlattened());
        }
        
        $content = array_map(function (InterfaceObjectInterface $ifcObj) {
            return $ifcObj->getTechDetails();
        }, $content);

        $this->write($format, $content);

        return $this;
    }

    /**
     * Write interface issue report
     * Currently focussed on CRUD rights
     *
     * @param string $format
     * @return \Ampersand\Misc\Reporter
     */
    public function reportInterfaceIssues(array $interfaces, string $format): Reporter
    {
        $content = [];
        foreach ($interfaces as $interface) {
            /** @var \Ampersand\Interfacing\Ifc $interface */
            foreach ($interface->getIfcObject()->getIfcObjFlattened() as $ifcObj) {
                /** @var InterfaceObjectInterface $ifcObj */
                $content = array_merge($content, $ifcObj->diagnostics());
            }
        }

        if (empty($content)) {
            $content[] = ['No issues found'];
        }

        $this->write($format, $content);
        
        return $this;
    }

    /**
     * Write conjunct usage report
     * Specifies which conjuncts are used by which rules, grouped by invariants, signals, and unused conjuncts
     *
     * @param \Ampersand\Rule\Conjunct[] $conjuncts
     * @param string $format
     * @return \Ampersand\Misc\Reporter
     */
    public function reportConjunctUsage(array $conjuncts, string $format): Reporter
    {
        $content = [];
        foreach ($conjuncts as $conj) {
            /** @var \Ampersand\Rule\Conjunct $conj */
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

        $this->write($format, $content);

        return $this;
    }

    /**
     * Write conjunct performance report
     *
     * @param \Ampersand\Rule\Conjunct[] $conjuncts
     * @param string $format
     * @return \Ampersand\Misc\Reporter
     */
    public function reportConjunctPerformance(array $conjuncts, string $format): Reporter
    {
        $content = [];
        
        // run all conjuncts (from - to)
        foreach ($conjuncts as $conjunct) {
            /** @var \Ampersand\Rule\Conjunct $conjunct */
            $startTimeStamp = microtime(true); // true means get as float instead of string
            $conjunct->evaluate();
            $endTimeStamp = microtime(true);
            set_time_limit((int) ini_get('max_execution_time')); // reset time limit counter
            
            $content[] = [ 'id' => $conjunct->getId()
                         , 'start' => round($startTimeStamp, 6)
                         , 'end' => round($endTimeStamp, 6)
                         , 'duration' => round($endTimeStamp - $startTimeStamp, 6)
                         , 'rules' => implode(';', $conjunct->getRuleNames())
                         ];
        }
        
        usort($content, function ($a, $b) {
            return $b['duration'] <=> $a['duration']; // uses php7 spaceship operator
        });

        $this->write($format, $content);

        return $this;
    }
}
