<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Ampersand\AmpersandApp;
use Ampersand\Misc\Otel;
use Ampersand\Plugs\MysqlDB\MysqlDB;
use Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Conjunct
{
    /**
     * Logger
     */
    private LoggerInterface $logger;

    /**
     * Reference to Ampersand app for which this conjunct is defined
     */
    protected AmpersandApp $app;

    /**
     * Database to evaluate conjuncts and store violation cache
     */
    protected MysqlDB $database;

    /**
     * Undocumented variable
     */
    protected CacheItemPoolInterface $cachePool;
    
    /**
     * Undocumented variable
     */
    protected CacheItemInterface $cacheItem;

    /**
     * Conjunct identifier
     */
    protected string $id;
    
    /**
     * Query to evaluate conjunct (i.e. get violations)
     */
    protected string $query;
    
    /**
     * List invariant rules that use this conjunct
     *
     * @var string[]
     */
    protected array $invRuleNames;
    
    /**
     * List signal rules that use this conjunct
     *
     * @var string[]
     */
    protected array $sigRuleNames;
    
    /**
     * Specifies if conjunct is already evaluated
     */
    protected bool $isEvaluated = false;

    /**
     * Shape class of this conjunct's violation query, as published by the compiler
     *
     * Null when the model was generated before the cost-profile contract existed
     * (Ampersand issue #1692); such a conjunct keeps the integral route.
     */
    protected ?CostClass $costClass = null;

    /**
     * Tables this conjunct's violation query reads in full
     *
     * @var string[]
     */
    protected array $scanTables = [];

    /**
     * Constructor
     */
    public function __construct(
        array $conjDef,
        AmpersandApp $app,
        LoggerInterface $logger,
        MysqlDB $database,
        CacheItemPoolInterface $cachePool
    )
    {
        $this->logger = $logger;
        $this->app = $app;
        $this->database = $database;
        
        $this->id = $conjDef['id'];
        $this->query = $conjDef['violationsSQL'];
        $this->invRuleNames = (array)$conjDef['invariantRuleNames'];
        $this->sigRuleNames = (array)$conjDef['signalRuleNames'];

        // Cost profile (Ampersand issue #1692). Optional: a model generated before the
        // contract has no such field, and an unknown class name is treated the same way,
        // so a newer compiler can add a class without breaking this framework.
        if (isset($conjDef['costProfile']['class'])) {
            $this->costClass = CostClass::tryFrom((string)$conjDef['costProfile']['class']);
            $this->scanTables = (array)($conjDef['costProfile']['scanTables'] ?? []);
        }

        $this->cachePool = $cachePool;
        $this->cacheItem = $cachePool->getItem($this->id);
    }
    
    /**
     * Function is called when object is treated as a string
     */
    public function __toString(): string
    {
        return $this->id;
    }

    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * Check is conjunct is used by/part of a signal rule
     */
    public function isSigConj(): bool
    {
        return !empty($this->sigRuleNames);
    }
    
    /**
     * Check is conjunct is used by/part of a invariant rule
     */
    public function isInvConj(): bool
    {
        return !empty($this->invRuleNames);
    }

    /**
     * Get list of rule names that use this conjunct
     *
     * @return string[]
     */
    public function getRuleNames(): array
    {
        return array_merge($this->sigRuleNames, $this->invRuleNames);
    }

    /**
     * The shape class the compiler assigned to this conjunct's violation query
     */
    public function getCostClass(): ?CostClass
    {
        return $this->costClass;
    }

    /**
     * The tables this conjunct's violation query reads in full
     *
     * @return string[]
     */
    public function getScanTables(): array
    {
        return $this->scanTables;
    }

    /**
     * Record that this conjunct holds, without running its query
     *
     * Reserved for the skip route of the cost gate: a conjunct whose violation set is
     * empty in every state the table layout admits. The cache is written exactly as an
     * evaluation would write it, so every reader downstream — the invariant check, the
     * signal notifications, the persisted cache — sees one kind of result.
     */
    public function markHolds(): self
    {
        $this->isEvaluated = true;
        $this->cacheItem->set([]);
        $this->cachePool->saveDeferred($this->cacheItem);

        return $this;
    }

    /**
     * Get query to evaluate conjunct violations
     */
    public function getQuery(): string
    {
        return str_replace('_SESSION', session_id(), $this->query); // Replace _SESSION var with current session id.
    }
    
    /**
     * Specificies if conjunct is part of UNI or INJ rule
     *
     * Temporary fuction to be able to skip uni and inj conj
     * TODO: remove after fix for issue #535
     */
    protected function isUniOrInjConj(): bool
    {
        return array_reduce($this->getRuleNames(), function (bool $carry, string $ruleName) {
            return ($carry || in_array(substr($ruleName, 0, 3), ['UNI', 'INJ']));
        }, false);
    }

    /**
     * Get violation pairs of this conjunct
     *
     * @return array{conjId: string, src: string, tgt: string}[]
     */
    public function getViolations(bool $forceReEvaluation = false): array
    {
        // Skipping evaluation of UNI and INJ conjuncts. TODO: remove after fix for issue #535
        if ($this->app->getSettings()->get('transactions.skipUniInjConjuncts') && $this->isUniOrInjConj()) {
            $this->logger->debug("Skipping conjunct '{$this}', because it is part of a UNI/INJ rule");
            return [];
        }
        
        // If re-evaluation is forced
        if ($forceReEvaluation || !$this->cacheItem->isHit()) {
            $this->evaluate();
            return $this->cacheItem->get();
        }

        // Otherwise get from cache
        $this->logger->debug("Conjunct is already evaluated, getting violations from cache");
        return $this->cacheItem->get();
    }
    
    /**
     * Evaluate conjunct and return array with violation pairs
     */
    public function evaluate(): self
    {
        $this->logger->debug("Evaluating conjunct '{$this->id}'");

        try {
            return Otel::span("conjunct {$this->id}", function ($span) {
                // Execute conjunct query
                $violations = array_map(function (array $pair) {
                    // Adds conjunct id to every pair
                    $pair['conjId'] = $this->id;
                    return $pair;
                }, $this->database->execute($this->getQuery()));

                $this->isEvaluated = true;
                $this->cacheItem->set($violations);
                $this->cachePool->saveDeferred($this->cacheItem);

                if (($count = count($violations)) == 0) {
                    $this->logger->debug("Conjunct '{$this->id}' holds");
                } else {
                    $this->logger->debug("Conjunct '{$this->id}' broken: {$count} violations");
                }
                $span->setAttribute('ampersand.violations', $count);

                return $this;
            }, ['ampersand.conjunct' => $this->id]);
        } catch (Exception $e) {
            $this->logger->error("Error evaluating conjunct '{$this->id}': " . $e->getMessage());
            throw $e;
        }
    }

    public function persistCacheItem(): void
    {
        $this->cachePool->save($this->cacheItem);
    }

    public function showInfo(): array
    {
        return [ 'id' => $this->id
               , 'invRules' => $this->invRuleNames
               , 'sigRules' => $this->sigRuleNames
               ];
    }
}
