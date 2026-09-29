<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Ampersand\AmpersandApp;
use Ampersand\Misc\Otel;
use Ampersand\Transaction;
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
     * Candidate queries for delta-scoped re-evaluation (issue Ampersand#1684),
     * keyed by relation signature. Null when the compiler did not emit them
     * (older compiler, or the violation term falls outside the supported class).
     *
     * @var array<string, array{relation: string, deltaTable: string, candidateSQL: string}>|null
     */
    protected ?array $deltaQueries = null;

    /**
     * True when this conjunct's cache rows were maintained by the delta
     * protocol in the current transaction; commit must then not overwrite
     * them wholesale from the (unevaluated) in-memory cache item.
     */
    protected bool $maintainedByDelta = false;

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

        if (isset($conjDef['deltaQueries'])) {
            $this->deltaQueries = [];
            foreach ((array)$conjDef['deltaQueries'] as $dq) {
                $this->deltaQueries[$dq['relation']] = $dq;
            }
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

                // Stamp this evaluation with the transaction's mutation counter, so the
                // transaction close can skip a re-evaluation when nothing changed since.
                // See transactions.skipCleanConjuncts (issue #443)
                Transaction::getCurrent()?->recordConjunctEvaluation($this);

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
        // Delta-maintained cache rows are already correct in the database
        // (updated inside the open transaction); a wholesale replace from the
        // unevaluated in-memory item would be wasted work at best.
        if ($this->maintainedByDelta) {
            return;
        }
        $this->cachePool->save($this->cacheItem);
    }

    /**
     * True when the compiler emitted candidate queries for this conjunct
     * (delta-scoped re-evaluation, issue Ampersand#1684)
     */
    public function hasDeltaQueries(): bool
    {
        return $this->deltaQueries !== null;
    }

    /**
     * True when a candidate query exists for the given relation signature
     */
    public function hasDeltaQueryFor(string $relationSignature): bool
    {
        return isset($this->deltaQueries[$relationSignature]);
    }

    /**
     * Maintain this conjunct's rows in the violation cache table with the
     * delta protocol (issue Ampersand#1684): per touched relation, delete the
     * cache rows in the candidate set and re-insert the violation rows
     * restricted to that candidate set. Runs inside the open DB transaction,
     * so the rows commit or roll back together with the data. The candidate
     * queries read the relation's delta table, which holds the pairs this
     * transaction touched.
     *
     * @param string[] $relationSignatures touched relations (each must have a candidate query)
     */
    public function deltaMaintain(array $relationSignatures, string $cacheTableName): void
    {
        $violSQL = $this->getQuery();
        foreach ($relationSignatures as $sig) {
            $dq = $this->deltaQueries[$sig] ?? null;
            if ($dq === null) {
                throw new Exception("Conjunct '{$this->id}' has no candidate query for relation '{$sig}'");
            }
            $candSQL = str_replace('_SESSION', session_id(), $dq['candidateSQL']);
            $inCands = fn (string $alias): string =>
                "({$alias}.\"src\", {$alias}.\"tgt\") IN (SELECT \"src\", \"tgt\" FROM ({$candSQL}) AS cand)";

            $this->database->execute(
                "DELETE c FROM \"{$cacheTableName}\" AS c"
                . " WHERE c.\"conjId\" = '{$this->id}' AND " . $inCands('c')
            );
            $this->database->execute(
                "INSERT INTO \"{$cacheTableName}\" (\"conjId\", \"src\", \"tgt\")"
                . " SELECT '{$this->id}', v.\"src\", v.\"tgt\" FROM ({$violSQL}) AS v"
                . " WHERE " . $inCands('v')
            );
        }
        $this->maintainedByDelta = true;

        // Refresh the in-memory cache item from the just-maintained table rows,
        // so the invariant check reads current data even when the item was
        // already memoized earlier in this request. Deliberately without
        // saveDeferred: the table rows are the source of truth here.
        $this->cacheItem->set($this->getViolationsFromDbCache($cacheTableName));

        $this->logger->debug("Conjunct '{$this->id}' cache maintained by delta protocol for relations: " . implode(', ', $relationSignatures));
    }

    /**
     * Read this conjunct's current rows from the violation cache table.
     * Inside an open transaction this sees the delta-maintained state.
     *
     * @return array{conjId: string, src: string, tgt: string}[]
     */
    public function getViolationsFromDbCache(string $cacheTableName): array
    {
        $rows = $this->database->execute(
            "SELECT \"conjId\", \"src\", \"tgt\" FROM \"{$cacheTableName}\" WHERE \"conjId\" = '{$this->id}'"
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Reset the per-transaction delta administration
     */
    public function resetDeltaMaintained(): void
    {
        $this->maintainedByDelta = false;
    }

    public function showInfo(): array
    {
        return [ 'id' => $this->id
               , 'invRules' => $this->invRuleNames
               , 'sigRules' => $this->sigRuleNames
               ];
    }
}
