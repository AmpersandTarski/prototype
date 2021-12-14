<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Ampersand\Rule\Violation;
use Generator;
use Psr\Cache\CacheItemPoolInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class RuleEngine
{

    /**
     * Function to get violations for a set of rules
     *
     * Note! Conjuncts are NOT re-evaluated
     *
     * @param \Ampersand\Rule\Rule[] $rules set of rules to check
     * @return \Ampersand\Rule\Violation[]
     */
    public static function getViolations(array $rules): array
    {
        // Evaluate rules
        $violations = [];
        foreach ($rules as $rule) {
            /** @var \Ampersand\Rule\Rule $rule */
            $violations = array_merge($violations, $rule->checkRule($forceReEvaluation = false));
        }
        return $violations;
    }
    
    /**
     * Get violations for set of rules from database cache
     *
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     * @param \Ampersand\Rule\Rule[] $rules set of rules for which to query the violations
     * @return \Ampersand\Rule\Violation[]
     */
    public static function getViolationsFromCache(CacheItemPoolInterface $cache, array $rules): array
    {
        // Determine conjuncts to select from database
        $conjuncts = [];
        $conjunctRuleMap = []; // needed because violations are instantiated per rule (not per conjunct)
        foreach ($rules as $rule) {
            /** @var \Ampersand\Rule\Rule $rule */
            foreach ($rule->getConjuncts() as $conjunct) {
                /** @var \Ampersand\Rule\Conjunct $conjunct */
                $conjunctRuleMap[$conjunct->getId()][] = $rule;
            }
            $conjuncts = array_merge($conjuncts, $rule->getConjuncts());
        }
        $conjuncts = array_unique($conjuncts); // remove duplicates
        
        if (empty($conjuncts)) {
            return [];
        }

        // Return violation
        $violations = [];
        foreach (self::getConjunctViolations($cache, $conjuncts) as $conjViolation) {
            foreach ($conjunctRuleMap[$conjViolation['conjId']] as $rule) {
                $violations[] = new Violation($rule, $conjViolation['src'], $conjViolation['tgt']);
            }
        }
        return $violations;
    }

    /**
     * Get conjunct violations (if possible from cache) for given set of conjuncts
     *
     * @param \Ampersand\Rule\Conjunct[] $conjuncts
     */
    protected static function getConjunctViolations(CacheItemPoolInterface $cache, array $conjuncts = []): Generator
    {
        // Foreach conjunct provided, check if there is a hit in cache (i.e. ->isHit())
        $hits = $nonHits = [];
        foreach ($conjuncts as $conjunct) {
            /** @var \Ampersand\Rule\Conjunct $conjunct */
            if ($cache->getItem($conjunct->getId())->isHit()) {
                $hits[] = $conjunct->getId();
            } else {
                $nonHits[] = $conjunct;
            }
        }

        // For all hits, use CacheItemPoolInterface->getItems()
        foreach ($cache->getItems($hits) as $cacheItem) {
            /** @var \Psr\Cache\CacheItemInterface $cacheItem */
            yield from $cacheItem->get();
        }

        // For all non-hits, get violations from Conjunct object
        foreach ($nonHits as $conjunct) {
            yield from $conjunct->getViolations();
        }
    }
}
