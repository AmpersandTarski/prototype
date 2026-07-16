<?php

namespace Ampersand\Controller;

use Ampersand\Core\Atom;
use Ampersand\Core\Concept;
use Ampersand\Core\Relation;
use Ampersand\Core\TType;
use Ampersand\Exception\BadRequestException;
use Ampersand\Interfacing\Ifc;
use Ampersand\Log\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

/**
 * Full-text search across all stored data of a prototype.
 *
 * Design (see docs/reference-material/search-module.md):
 *  - The unit of search is the *column* in which atoms are stored. Every relation knows
 *    the table and the two columns (src, tgt) in which it is administrated, together with
 *    the concept — and thereby the TType — of each side. Iterating over relations therefore
 *    yields every stored column uniformly, regardless of whether the Ampersand compiler put
 *    a (univalent) relation in a concept's "broad" table or in its own binary table.
 *  - Searching is TType-aware (requirement 1): a column is only queried when the search term
 *    is a plausible value for that column's TType. The term "983" is searched in INTEGER
 *    columns *and* in ALPHANUMERIC columns; the term "Solanum" only in alphanumeric columns.
 *    This is invisible to the user.
 *  - OBJECT atoms carry no meaningful content beyond their identity, so OBJECT columns are
 *    never searched (requirement 2). The same holds for PASSWORD (security), BINARY data and
 *    BOOLEAN/TYPEOFONE (no full-text meaning).
 *  - A match in a scalar column belongs to the entity (the OBJECT atom) on the other side of
 *    the relation. That entity atom is the search result (requirement 3), enriched with the
 *    interfaces that can display it (requirement 4) via AmpersandApp::getInterfacesToReadConcept.
 */
class SearchController extends AbstractController
{
    /**
     * Minimum length of a search term. Shorter terms match too much to be useful.
     */
    private const MIN_TERM_LENGTH = 2;

    /**
     * Maximum number of matching rows fetched per searched column.
     */
    private const PER_COLUMN_LIMIT = 100;

    /**
     * Maximum number of distinct result atoms returned to the client.
     */
    private const MAX_RESULTS = 200;

    /**
     * Maximum number of "matched field" hints kept per result atom (for UI context).
     */
    private const MAX_MATCHES_PER_RESULT = 5;

    /**
     * GET /api/v1/search?q=<term>
     *
     * Returns the entity atoms whose stored data contains the search term, each with the
     * interfaces in which they can be opened.
     */
    public function search(Request $request, Response $response): Response
    {
        $term = trim((string) $request->getQueryParam('q', ''));

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            throw new BadRequestException(
                "Please provide a search term of at least " . self::MIN_TERM_LENGTH . " characters"
            );
        }

        $db = $this->app->getDefaultStorage();
        $likePattern = $this->buildLikePattern($term);

        // Accumulate results keyed by "<conceptName>:<atomId>" to deduplicate entities that
        // match in more than one column.
        $results = [];
        // Cache per concept: avoids recomputing the (atom-independent) interface list and label.
        $ifcCache = [];
        $truncated = false;

        foreach ($this->app->getModel()->getRelations() as $relation) {
            $column = $this->scalarColumnToSearch($relation, $term);
            if ($column === null) {
                continue; // not a searchable scalar column for this term, or both sides OBJECT/scalar
            }

            // Only search data that this session may read: an atom qualifies as a search result
            // when the session has at least one interface to open it in (requirement 4). Without
            // this check the stored values themselves (see 'matches' below) are returned to any
            // session, regardless of its roles. Skipping here also avoids querying the column.
            if (empty($this->interfacesToReadConcept($column['entityConcept'], $ifcCache))) {
                continue;
            }

            try {
                $rows = $db->execute($this->buildColumnQuery($column, $likePattern));
            } catch (Throwable $e) {
                // A single malformed column must not break the whole search.
                Logger::getLogger('SEARCH')->warning(
                    "Search skipped column {$column['table']}.{$column['searchCol']}: {$e->getMessage()}"
                );
                continue;
            }

            foreach ((array) $rows as $row) {
                $atomId = $row['atomId'];
                $entityConcept = $column['entityConcept'];
                $key = $entityConcept->getId() . ':' . $atomId;

                if (!isset($results[$key])) {
                    if (count($results) >= self::MAX_RESULTS) {
                        $truncated = true;
                        break 2; // stop searching entirely once the result cap is reached
                    }
                    $results[$key] = $this->newResult($atomId, $entityConcept, $ifcCache);
                }

                $this->addMatch($results[$key], $relation->name, (string) $row['matchedValue']);
            }
        }

        // Sort by concept label, then by atom label, for a predictable presentation.
        $resultList = array_values($results);
        usort($resultList, function (array $a, array $b) {
            return [$a['concept'], $a['_label_']] <=> [$b['concept'], $b['_label_']];
        });

        return $response->withJson(
            [
                'term' => $term,
                'truncated' => $truncated,
                'results' => $resultList,
            ],
            200,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Decide whether (and how) a relation contributes a scalar column to the search for this term.
     *
     * Returns null when the relation has no searchable scalar column for this term. Otherwise
     * returns an array describing the column and the entity that owns its values:
     *   [ 'table', 'searchCol', 'entityCol', 'entityConcept' ]
     */
    private function scalarColumnToSearch(Relation $relation, string $term): ?array
    {
        $table = $relation->getMysqlTable();
        $srcSearchable = $this->ttypeMatchesTerm($relation->srcConcept->type, $term);
        $tgtSearchable = $this->ttypeMatchesTerm($relation->tgtConcept->type, $term);

        // We can only attribute a match to an entity when exactly one side is an OBJECT atom and
        // the other side is a searchable scalar value. Object*object and scalar*scalar relations
        // have no single entity to navigate to and are skipped.
        if ($relation->srcConcept->isObject() && $tgtSearchable && !$relation->tgtConcept->isObject()) {
            return [
                'table' => $table->getName(),
                'searchCol' => $table->tgtCol()->getName(),
                'entityCol' => $table->srcCol()->getName(),
                'entityConcept' => $relation->srcConcept,
            ];
        }

        if ($relation->tgtConcept->isObject() && $srcSearchable && !$relation->srcConcept->isObject()) {
            return [
                'table' => $table->getName(),
                'searchCol' => $table->srcCol()->getName(),
                'entityCol' => $table->tgtCol()->getName(),
                'entityConcept' => $relation->tgtConcept,
            ];
        }

        return null;
    }

    /**
     * TType-aware filter (requirement 1): is the term a plausible value for the given TType?
     *
     * Alphanumeric columns always qualify. Numeric/temporal columns only qualify when the term
     * could occur as a substring of such a value, so we never run a text search against, say, an
     * INTEGER column with a term containing letters. OBJECT, PASSWORD, BINARY, BOOLEAN and
     * TYPEOFONE are never searched.
     */
    private function ttypeMatchesTerm(TType $type, string $term): bool
    {
        return match ($type) {
            TType::ALPHANUMERIC,
            TType::BIGALPHANUMERIC,
            TType::HUGEALPHANUMERIC => true,
            TType::INTEGER => (bool) preg_match('/^[0-9]+$/', $term),
            TType::FLOAT => (bool) preg_match('/^[0-9]+([.,][0-9]+)?$/', $term),
            TType::DATE,
            TType::DATETIME => (bool) preg_match('/^[0-9][0-9T:\-\s]*$/', $term),
            default => false, // OBJECT, PASSWORD, BINARY*, BOOLEAN, TYPEOFONE
        };
    }

    /**
     * Build the SQL for one column: distinct owning entities whose value matches the term.
     */
    private function buildColumnQuery(array $column, string $likePattern): string
    {
        return "SELECT DISTINCT \"{$column['entityCol']}\" AS \"atomId\", \"{$column['searchCol']}\" AS \"matchedValue\""
            . " FROM \"{$column['table']}\""
            . " WHERE \"{$column['entityCol']}\" IS NOT NULL"
            . " AND \"{$column['searchCol']}\" LIKE '{$likePattern}'"
            . " LIMIT " . self::PER_COLUMN_LIMIT;
    }

    /**
     * Turn a raw user term into a LIKE pattern that matches it as a literal substring.
     *
     * Two escaping layers are applied: addcslashes neutralises the LIKE metacharacters (% _ \)
     * so the user cannot inject pattern wildcards, and escape() then makes the whole thing a
     * safe SQL string literal. The result is wrapped with % for substring matching.
     */
    private function buildLikePattern(string $rawTerm): string
    {
        $literal = $this->app->getDefaultStorage()->escape(addcslashes($rawTerm, '\\%_')) ?? '';
        return "%{$literal}%";
    }

    /**
     * Build a fresh result entry for an entity atom, including its display label and the
     * interfaces in which it can be opened (requirement 4). Per-concept data is cached.
     */
    private function newResult(string $atomId, Concept $entityConcept, array &$ifcCache): array
    {
        $atom = new Atom($atomId, $entityConcept);

        return [
            '_id_' => $atomId,
            '_label_' => $atom->getLabel(),
            '_ifcs_' => $this->interfacesToReadConcept($entityConcept, $ifcCache),
            'concept' => $entityConcept->getLabel(),
            'conceptId' => $entityConcept->getId(),
            'matches' => [],
        ];
    }

    /**
     * The interfaces in which this session can open atoms of the given concept, cached per concept.
     *
     * An empty list means the session may not read the concept at all. It is both the
     * authorisation criterion for searching a column and the `_ifcs_` presented per result.
     *
     * @return array<array{id: string, label: string}>
     */
    private function interfacesToReadConcept(Concept $entityConcept, array &$ifcCache): array
    {
        $conceptId = $entityConcept->getId();
        if (!isset($ifcCache[$conceptId])) {
            $ifcCache[$conceptId] = array_values(array_map(
                fn (Ifc $ifc) => ['id' => $ifc->getId(), 'label' => $ifc->getLabel()],
                $this->app->getInterfacesToReadConcept($entityConcept)
            ));
        }

        return $ifcCache[$conceptId];
    }

    /**
     * Record a "matched field" hint on a result, deduplicated by relation and capped for the UI.
     */
    private function addMatch(array &$result, string $field, string $value): void
    {
        foreach ($result['matches'] as $match) {
            if ($match['field'] === $field) {
                return; // already recorded this field for this entity
            }
        }
        if (count($result['matches']) >= self::MAX_MATCHES_PER_RESULT) {
            return;
        }
        $result['matches'][] = ['field' => $field, 'value' => $value];
    }
}
