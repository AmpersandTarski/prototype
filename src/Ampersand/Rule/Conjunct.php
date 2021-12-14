<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Exception;
use Ampersand\AmpersandApp;
use Ampersand\Plugs\MysqlDB\MysqlDB;
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
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to Ampersand app for which this conjunct is defined
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $app;

    /**
     * Database to evaluate conjuncts and store violation cache
     *
     * @var \Ampersand\Plugs\MysqlDB\MysqlDB
     */
    protected $database;

    /**
     * Undocumented variable
     *
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    protected $cachePool;
    
    /**
     * Undocumented variable
     *
     * @var \Psr\Cache\CacheItemInterface
     */
    protected $cacheItem;

    /**
     * Conjunct identifier
     *
     * @var string
     */
    protected $id;
    
    /**
     * Query to evaluate conjunct (i.e. get violations)
     *
     * @var string
     */
    protected $query;
    
    /**
     * List invariant rules that use this conjunct
     *
     * @var string[]
     */
    protected $invRuleNames;
    
    /**
     * List signal rules that use this conjunct
     *
     * @var string[]
     */
    protected $sigRuleNames;
    
    /**
     * Specifies if conjunct is already evaluated
     *
     * @var bool
     */
    protected $isEvaluated = false;
    
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

            return $this;
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
