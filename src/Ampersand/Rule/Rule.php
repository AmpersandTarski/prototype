<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Exception;
use Ampersand\AmpersandApp;
use Ampersand\Plugs\ViewPlugInterface;
use Psr\Log\LoggerInterface;
use Ampersand\Rule\RuleType;
use Ampersand\Core\Concept;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Rule
{
    /**
     * Logger
     */
    private LoggerInterface $logger;

    /**
     * Reference to Ampersand app for which this rule is defined
     */
    protected AmpersandApp $ampersandApp;

    /**
     * Dependency injection of an ViewPlug implementation
     */
    protected ViewPlugInterface $plug;

    /**
     * Rule identifier
     */
    protected string $id;

    /**
     * Rule label as defined in Ampersand script
     * @var string
     */
    protected string $label;
    
    /**
     * The file and line number of the Ampersand script where this rule is defined
     */
    protected string $origin;
    
    /**
     * The formalized rule in adl
     */
    protected string $ruleAdl;
    
    /**
     * The source concept of this rule
     */
    public Concept $srcConcept;
    
    /**
     * The target concept of this rule
     */
    public Concept $tgtConcept;
    
    /**
     * The meaning of this rule (provided in natural language by the Ampersand engineer)
     */
    protected string $meaning;
    
    /**
     * The violation message to display (provided in natural language by the Ampersand engineer)
     */
    protected string $message;
    
    /**
     * List of conjuncts of which this rule is made of
     *
     * @var \Ampersand\Rule\Conjunct[]
     */
    protected array $conjuncts = [];
    
    /**
     * List with segments to build violation messages
     *
     * @var \Ampersand\Rule\ViolationSegment[]
     */
    protected array $violationSegments = [];
    
    /**
     * Specifies the type of rule
     */
    protected RuleType $type;
    
    /**
     * Constructor
    */
    public function __construct(
        array $ruleDef,
        ViewPlugInterface $plug,
        RuleType $type,
        AmpersandApp $app,
        LoggerInterface $logger
    )
    {
        $this->logger = $logger;
        $this->ampersandApp = $app;
        $this->plug = $plug;
        $this->type = $type;
        
        $this->id = $ruleDef['name'];
        $this->label = $ruleDef['label'];
        
        $this->origin = $ruleDef['origin'];
        $this->ruleAdl = $ruleDef['ruleAdl'];
        
        $this->srcConcept = $app->getModel()->getConcept($ruleDef['srcConceptName']);
        $this->tgtConcept = $app->getModel()->getConcept($ruleDef['tgtConceptName']);
        
        $this->meaning = $ruleDef['meaning'];
        $this->message = $ruleDef['message'];
        
        // Conjuncts
        foreach ($ruleDef['conjunctIds'] as $conjId) {
            $this->conjuncts[] = $app->getModel()->getConjunct($conjId);
        }
        
        // Violation segments
        foreach ((array)$ruleDef['pairView'] as $segment) {
            $this->violationSegments[] = new ViolationSegment($segment, $this);
        }
    }
    
    /**
     * Function is called when object is treated as a string
     */
    public function __toString(): string
    {
        return $this->label;
    }

    protected function toLogString(): string
    {
        return "{$this->id} (label: {$this->label})";
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPlug(): ViewPlugInterface
    {
        return $this->plug;
    }

    public function getOrigin(): string
    {
        return $this->origin;
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
     */
    public function isSignalRule(): bool
    {
        return $this->type === RuleType::SIG;
    }

    /**
     * Specifies is rule is a invariant rule
     */
    public function isInvariantRule(): bool
    {
        return $this->type === RuleType::INV;
    }
    
    /**
     * Get message to tell that a rule is broken
     */
    public function getViolationMessage(): string
    {
        return $this->message ? $this->message : "Violation of rule '{$this->label}'";
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
     * @return \Ampersand\Rule\Violation[]
     */
    public function checkRule(bool $forceReEvaluation = true): array
    {
        $this->logger->debug("Checking rule '{$this->toLogString()}'");
         
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
                $this->logger->debug("Rule '{$this->toLogString()}' holds");
            }
    
            return $violations;
        } catch (Exception $e) {
            $this->logger->error("Error while evaluating rule '{$this->toLogString()}': {$e->getMessage()}");
            $this->ampersandApp->userLog()->error("Error while evaluating rule '{$this->label}'");
            return [];
        }
    }
}
