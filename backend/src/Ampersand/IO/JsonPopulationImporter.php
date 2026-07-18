<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Ampersand\Core\Atom;
use Ampersand\Core\Link;
use Ampersand\Exception\BadRequestException;
use Ampersand\Model;
use Generator;
use JsonMachine\Exception\JsonMachineException;
use JsonMachine\Exception\PathNotFoundException;
use JsonMachine\Items;
use Psr\Log\LoggerInterface;

/**
 * Streaming importer for Ampersand population files (JSON).
 *
 * Reads the same format as Population::loadFromPopulationFile():
 *
 *   { "atoms": [ {"concept": "C", "atoms": ["a1", ...]}, ... ]
 *   , "links": [ {"relation": "r[S*T]", "links": [{"src": "a", "tgt": "b"}, ...]}, ... ]
 *   }
 *
 * Contrary to Population::loadFromPopulationFile()/import(), which materializes the
 * complete file THREE times (raw string, decoded object tree, Atom/Link object arrays),
 * this importer streams the file with an incremental JSON parser and calls
 * Atom::add()/Link::add() per item, discarding each item immediately afterwards.
 *
 * Memory contract: usage is bounded by the LARGEST SINGLE BLOCK (one concept's atom
 * list or one relation's link list), not by the total population size. Key order
 * within a block is irrelevant (each block is decoded as a whole), so files produced
 * by Population::export() — which emits "atoms" before "concept" — import fine.
 *
 * Semantics are identical to the in-memory path: first all atoms, then all links
 * (two sequential passes over the file), and each item goes through the same
 * Atom::add()/Link::add() as before. The all-or-nothing guarantee is unchanged:
 * this importer runs inside the caller's transaction; invariants are evaluated
 * on the end result at Transaction::close().
 *
 * @author Stef Joosten
 */
class JsonPopulationImporter
{
    /**
     * Log progress and reset the time limit counter every this many items
     * (same purpose as in Population::import(), which uses 100)
     */
    protected const PROGRESS_INTERVAL = 1000;

    public function __construct(
        protected Model $model,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * Import a population file, streaming: memory is independent of the file size.
     * Must be called within an open transaction; the caller closes it (and thereby
     * decides commit/rollback based on the invariants).
     */
    public function importFile(string $filePath): void
    {
        $this->logger->info("Start streaming import of population file");

        $atomBlocks = $this->blocks($filePath, '/atoms/-', 'concept', 'atoms');
        $countAtoms = $this->importAtomBlocks($atomBlocks);
        $hasAtomsKey = $atomBlocks->getReturn();

        $linkBlocks = $this->blocks($filePath, '/links/-', 'relation', 'links');
        $countLinks = $this->importLinkBlocks($linkBlocks);
        $hasLinksKey = $linkBlocks->getReturn();

        // A file with neither an "atoms" nor a "links" key is not a population file (any other
        // YAML/JSON document lands here). Reject it with a plain message — as the compiler does —
        // instead of committing nothing and reporting false success. This keeps compile-time and
        // run-time import consistent, and JSON and YAML uploads behave the same (both go through
        // this method). An empty-but-well-formed population ("atoms":[], "links":[]) still has the
        // keys, so it is accepted.
        if (!$hasAtomsKey && !$hasLinksKey) {
            throw new BadRequestException("Invalid population file: expected an 'atoms' and/or 'links' key");
        }

        $this->logger->info("End streaming import: {$countAtoms} atoms and {$countLinks} links imported");
    }

    /**
     * Yield [name, list] per block under the given json pointer, one block at a time.
     *
     * JsonMachine iterates the MEMBERS of each matched block ('concept' => "C",
     * 'atoms' => [...], next block, ...); this state machine pairs them back into
     * blocks, in whatever key order they appear (Population::export() emits the
     * list before the name). Unknown keys are ignored (forward compatible).
     * A missing top-level key yields nothing (e.g. a file with only links). The generator's
     * return value (Generator::getReturn()) tells the caller whether the top-level key was
     * PRESENT at all: true when the pointer resolved (even to an empty list), false when it
     * was absent. This lets importFile() distinguish an empty population from a non-population
     * file without a second pass.
     *
     * @return \Generator<array{0: string, 1: array}, mixed, bool>
     */
    protected function blocks(string $filePath, string $pointer, string $nameKey, string $listKey): Generator
    {
        $name = null;
        $list = null;
        try {
            foreach (Items::fromFile($filePath, ['pointer' => $pointer]) as $key => $value) {
                if ($key === $nameKey) {
                    if ($name !== null) {
                        throw new BadRequestException("Invalid population file: block without '{$listKey}' before next '{$nameKey}'");
                    }
                    $name = (string) $value;
                } elseif ($key === $listKey) {
                    if ($list !== null) {
                        throw new BadRequestException("Invalid population file: block without '{$nameKey}' before next '{$listKey}'");
                    }
                    $list = is_array($value) ? $value : [];
                }
                if ($name !== null && $list !== null) {
                    yield [$name, $list];
                    $name = null;
                    $list = null;
                }
            }
        } catch (PathNotFoundException $e) {
            return false; // toplevel key absent: empty stream
        } catch (JsonMachineException $e) {
            throw new BadRequestException("Invalid population file: {$e->getMessage()}", previous: $e);
        }
        if ($name !== null || $list !== null) {
            throw new BadRequestException("Invalid population file: incomplete block (missing '{$nameKey}' or '{$listKey}')");
        }
        return true; // toplevel key was present (possibly with an empty list)
    }

    /**
     * @param iterable<array{0: string, 1: array}> $blocks pairs of (concept name, atom ids)
     * @return int number of atoms imported
     */
    protected function importAtomBlocks(iterable $blocks): int
    {
        $count = 0;
        foreach ($blocks as [$conceptName, $atomIds]) {
            $concept = $this->model->getConcept($conceptName);
            foreach ($atomIds as $atomId) {
                (new Atom($atomId, $concept))->add();
                $this->tick(++$count, 'atoms');
            }
        }
        return $count;
    }

    /**
     * @param iterable<array{0: string, 1: array}> $blocks pairs of (relation signature, link pairs)
     * @return int number of links imported
     */
    protected function importLinkBlocks(iterable $blocks): int
    {
        $count = 0;
        foreach ($blocks as [$signature, $pairs]) {
            $relation = $this->model->getRelation($signature);
            foreach ($pairs as $pair) {
                if (!isset($pair->src) || !isset($pair->tgt)) {
                    throw new BadRequestException("Invalid population file: link pair without src/tgt for relation {$signature}");
                }
                (new Link($relation, new Atom($pair->src, $relation->srcConcept), new Atom($pair->tgt, $relation->tgtConcept)))->add();
                $this->tick(++$count, 'links');
            }
        }
        return $count;
    }

    protected function tick(int $count, string $what): void
    {
        if ($count % self::PROGRESS_INTERVAL === 0) {
            $this->logger->debug("...{$count} {$what} imported");
            // reset time limit counter to handle large amounts of population queries
            set_time_limit((int) ini_get('max_execution_time'));
        }
    }
}
