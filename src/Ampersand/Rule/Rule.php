<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Exception;
use Ampersand\AmpersandApp;
use Ampersand\Core\Concept;
use Ampersand\Plugs\ViewPlugInterface;
use Psr\Log\LoggerInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Rule
{
    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to Ampersand app for which this rule is defined
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $ampersandApp;

    /**
     * Dependency injection of an ViewPlug implementation
     *
     * @var \Ampersand\Plugs\ViewPlugInterface
     */
    protected $plug;

    /**
     * Rule identifier
     *
     * @var string
     */
    protected $id;
    
    /**
     * The file and line number of the Ampersand script where this rule is defined
     *
     * @var string
     */
    protected $origin;
    
    /**
     * The formalized rule in adl
     *
     * @var string
     */
    protected $ruleAdl;
    
    /**
     * The source concept of this rule
     *
     * @var \Ampersand\Core\Concept
     */
    public $srcConcept;
    
    /**
     * The target concept of this rule
     *
     * @var \Ampersand\Core\Concept
     */
    public $tgtConcept;
    
    /**
     * The meaning of this rule (provided in natural language by the Ampersand engineer)
     *
     * @var string
     */
    protected $meaning;
    
    /**
     * The violation message to display (provided in natural language by the Ampersand engineer)
     *
     * @var string
     */
    protected $message;
    
    /**
     * List of conjuncts of which this rule is made of
     *
     * @var \Ampersand\Rule\Conjunct[]
     */
    protected $conjuncts = [];
    
    /**
     * List with segments to build violation messages
     *
     * @var \Ampersand\Rule\ViolationSegment[]
     */
    protected $violationSegments = [];
    
    /**
     * Specifies the type of rule (signal or invariant)
     *
     * @var string
     */
    protected $type;
    
    /**
     * Rule constructor
     *
     * @param array $ruleDef
     * @param \Ampersand\Plugs\ViewPlugInterface $plug
     * @param string $type specifies if it is a signal (sig) or invariant (inv) rule
     * @param \Ampersand\AmpersandApp $app
     * @param \Psr\Log\LoggerInterface $logger
    */
    public function __construct(array $ruleDef, ViewPlugInterface $plug, string $type, AmpersandApp $app, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->ampersandApp = $app;
        $this->plug = $plug;
        
        $this->id = $ruleDef['name'];
        
        $this->origin = $ruleDef['origin'];
        $this->ruleAdl = $ruleDef['ruleAdl'];
        
        $this->srcConcept = Concept::getConcept($ruleDef['srcConceptId']);
        $this->tgtConcept = Concept::getConcept($ruleDef['tgtConceptId']);
        
        $this->meaning = $ruleDef['meaning'];
        $this->message = $ruleDef['message'];
        
        // Conjuncts
        foreach ($ruleDef['conjunctIds'] as $conjId) {
            $this->conjuncts[] = Conjunct::getConjunct($conjId);
        }
        
        // Violation segments
        foreach ((array)$ruleDef['pairView'] as $segment) {
            $this->violationSegments[] = new ViolationSegment($segment, $this);
        }
        
        // Set type of rule
        if (!in_array($type, ['signal', 'invariant'])) {
            throw new Exception("Unsupported rule type. Allowed types are signal or invariant", 500);
        }
        $this->type = $type;
    }
    
    /**
     * Function is called when object is treated as a string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPlug(): ViewPlugInterface
    {
        return $this->plug;
    }

    /**
     * List of conjuncts of which this rule is made of
     *
     * @return \Ampersand\Rule\Conjunct[]
     */
    public function getConjuncts(): array
    {
        return $this->conjuncts;
    }

    /**
     * Specifies if rule is a signal rule
     *
     * @return boolean
     */
    public function isSignalRule(): bool
    {
        return $this->type === 'signal';
    }

    /**
     * Specifies is rule is a invariant rule
     *
     * @return boolean
     */
    public function isInvariantRule(): bool
    {
        return $this->type === 'invariant';
    }
    
    /**
     * Get message to tell that a rule is broken
     *
     * @return string
     */
    public function getViolationMessage(): string
    {
        return $this->message ? $this->message : "Violation of rule '{$this->id}'";
    }

    /**
     * Get list of all violation segment definitions for this rule
     *
     * @return \Ampersand\Rule\ViolationSegment[]
     */
    public function getViolationSegments(): array
    {
        return $this->violationSegments;
    }
    
    /**
     * Check rule and return violations
     *
     * @param bool $forceReEvaluation
     * @return \Ampersand\Rule\Violation[]
     */
    public function checkRule(bool $forceReEvaluation = true): array
    {
        $this->logger->debug("Checking rule '{$this->id}'");
         
        try {
            $violations = [];
    
            // Evaluate conjuncts of this rule
            foreach ($this->conjuncts as $conjunct) {
                foreach ($conjunct->getViolations($forceReEvaluation) as $violation) {
                    $violations[] = new Violation($this, $violation['src'], $violation['tgt']);
                }
            }
            
            // If no violations => rule holds
            if (empty($violations)) {
                $this->logger->debug("Rule '{$this}' holds");
            }
    
            return $violations;
        } catch (Exception $e) {
            $this->logger->error("Error while evaluating rule '{$this}': {$e->getMessage()}");
            $this->ampersandApp->userLog()->error("Error while evaluating rule");
            return [];
        }
    }
}
