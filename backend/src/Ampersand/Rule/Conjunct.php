<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Ampersand\AmpersandApp;
use Ampersand\Exception\FatalException;
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
     * True when the violations held by this conjunct come from a previous dry-run round
     * (handed back by the client) instead of from a query. See DesignChoices OK-10.
     */
    protected bool $verdictIsCarried = false;

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

    /**
     * Adopt the violations this conjunct had in the previous dry-run round.
     *
     * A dry run of a transactional interface re-applies the whole buffered edit set, but only the
     * operations added since the previous round can change a verdict. For every other affected
     * conjunct the previous round's answer still holds, and the client hands it back
     * (DesignChoices OK-10). The violations land in the in-memory cache item only, so
     * Rule::checkRule() reads them without a query and without touching the violation cache
     * table. Persisting them is refused outright, see persistCacheItem().
     *
     * @param array{src: string, tgt: string}[] $pairs
     */
    public function useCarriedViolations(array $pairs): void
    {
        $this->cacheItem->set(array_map(function (array $pair): array {
            return ['conjId' => $this->id, 'src' => (string) $pair['src'], 'tgt' => (string) $pair['tgt']];
        }, $pairs));

        $this->isEvaluated = true;
        $this->verdictIsCarried = true;

        $this->logger->debug("Conjunct '{$this->id}' takes its verdict from the previous dry-run round");
    }

    /**
     * The violations this conjunct holds within the current request, or null when it neither
     * evaluated nor adopted a verdict. Never falls back on the violation cache table.
     *
     * @return array{conjId: string, src: string, tgt: string}[]|null
     */
    public function getViolationsOfThisRequest(): ?array
    {
        return $this->isEvaluated ? $this->cacheItem->get() : null;
    }

    public function persistCacheItem(): void
    {
        // A carried verdict describes an uncommitted, rolled-back state and belongs to the
        // advisory only. It can only be reached on a dry run, which never commits; a commit
        // that gets here anyway would write violations that were never queried.
        if ($this->verdictIsCarried) {
            throw new FatalException("Refusing to persist conjunct '{$this->id}': its verdict was carried from a dry-run round, not evaluated");
        }

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
