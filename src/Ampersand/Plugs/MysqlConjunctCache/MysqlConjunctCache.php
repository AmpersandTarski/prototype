<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs\MysqlConjunctCache;

use Psr\Cache\CacheItemPoolInterface;
use Ampersand\Plugs\MysqlDB\MysqlDB;
use Psr\Cache\CacheItemInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class MysqlConjunctCache implements CacheItemPoolInterface
{
    /**
     * Mysql database where conjunct violations are cached
     */
    protected MysqlDB $database;

    /**
     * Name of table where conjunct violations are cached.
     *
     * Note! Table structure must at least contain the following columns:
     * "conjId" VARCHAR(255) NOT NULL,
     * "src" VARCHAR(255) NOT NULL,
     * "tgt" VARCHAR(255) NOT NULL
     */
    protected string $tableName;

    /**
     * @var \Ampersand\Plugs\MysqlConjunctCache\MysqlConjunctCacheItem[]
     */
    protected array $deferred = [];

    /**
     * Constructor
     */
    public function __construct(MysqlDB $database, string $tableName = '__conj_violation_cache__')
    {
        $this->database = $database;
        $this->tableName = $tableName;
    }

    /**
     * Make sure to commit before we destruct.
     */
    public function __destruct()
    {
        // $this->commit(); Don't automatically commit. This is done by Transaction class
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null.
     *
     * @param string $key
     *   The key for which to return the corresponding Cache Item.
     *
     * @return \Psr\Cache\CacheItemInterface
     *   The corresponding Cache Item.
     */
    public function getItem($key): CacheItemInterface
    {
        if (isset($this->deferred[$key])) {
            return $this->deferred[$key];
        }

        return new MysqlConjunctCacheItem($key, $this->getConjunctViolations(...));
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys
     *   An indexed array of keys of items to retrieve.
     *
     * @return array|\Traversable
     *   A traversable collection of Cache Items keyed by the cache keys of
     *   each item. A Cache item will be returned for each key, even if that
     *   key is not found. However, if no keys are specified then an empty
     *   traversable MUST be returned instead.
     */
    public function getItems(array $keys = []): array|\Traversable
    {
        // Query conjunct cache for given keys, group by conjId
        $violations = [];
        foreach ($this->getConjunctViolations($keys) as $row) {
            $violations[$row['conjId']][] = $row;
        }

        foreach ($keys as $key) {
            if (isset($this->deferred[$key])) {
                // Yield deferred CacheItem
                yield $this->deferred[$key];
            } else {
                // Yield new CacheItem, set conjunct cache
                $func = $this->getConjunctViolations(...);
                yield (new MysqlConjunctCacheItem($key, $func))->set($violations[$key] ?? []);
            }
        }
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance reasons.
     * This could result in a race condition with CacheItemInterface::get(). To avoid
     * such situation use CacheItemInterface::isHit() instead.
     *
     * @param string $key
     *   The key for which to check existence.
     *
     * @return bool
     *   True if item exists in the cache, false otherwise.
     */
    public function hasItem($key): bool
    {
        return true; // Always return true, because a CacheItem for each conjunct always exists, even when there are no violations.
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     *   True if the pool was successfully cleared. False if there was an error.
     */
    public function clear(): bool
    {
        $this->deferred = [];

        // Do not use TRUNCATE TABLE because TRUNCATE is DDL and NOT DML like DELETE. This will cause implicit database COMMIT.
        return $this->database->execute("DELETE FROM \"{$this->tableName}\"");
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     *   The key to delete.
     *
     * @return bool
     *   True if the item was successfully removed. False if there was an error.
     */
    public function deleteItem($key): bool
    {
        return $this->deleteItems([$key]);
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param string[] $keys
     *   An array of keys that should be removed from the pool.
     *
     * @return bool
     *   True if the items were successfully removed. False if there was an error.
     */
    public function deleteItems(array $keys): bool
    {
        // Delete form deferred
        foreach ($keys as $key) {
            unset($this->deferred[$key]);
        }

        // Delete existing conjunct violations in cache for given conjunctIds ($keys)
        $keyString = implode(',', array_map(function ($key) {
            return "'{$key}'"; // surround with single quotes
        }, $keys));
        return $this->database->execute("DELETE FROM \"{$this->tableName}\" WHERE \"conjId\" IN ({$keyString})");
    }

    /**
     * Persists a cache item immediately.
     *
     * @param \Psr\Cache\CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   True if the item was successfully persisted. False if there was an error.
     */
    public function save(CacheItemInterface $item): bool
    {
        $this->deleteItem($item->getKey());

        $insertValues = array_map(function ($violation) {
            return "('{$violation['conjId']}', '" . $this->database->escape($violation['src']) . "', '" . $this->database->escape($violation['tgt']) . "')";
        }, $item->get());

        // Directly return true when nothing to insert
        if (empty($insertValues)) {
            return true;
        }

        // Add new conjunct violation to database
        $query = "INSERT IGNORE INTO \"{$this->tableName}\" (\"conjId\", \"src\", \"tgt\") VALUES " . implode(',', $insertValues);

        return $this->database->execute($query);
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param \Psr\Cache\CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   False if the item could not be queued or if a commit was attempted and failed. True otherwise.
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     *   True if all not-yet-saved items were successfully saved or there were none. False otherwise.
     */
    public function commit(): bool
    {
        $saved = true;
        foreach ($this->deferred as $item) {
            if (!$this->save($item)) {
                $saved = false;
            }
        }
        $this->deferred = [];

        return $saved;
    }

    /**
     * Undocumented function
     *
     * @param string[] $conjunctIds
     */
    public function getConjunctViolations(array $conjunctIds = []): array
    {
        $whereClause = implode("','", $conjunctIds); // returns string "<conjId1>,<conjId2>,<etc>"
        $query = "SELECT * FROM \"{$this->tableName}\" WHERE \"conjId\" IN ('{$whereClause}')";
        return $this->database->execute($query); // [['conjId' => '<conjId>', 'src' => '<srcAtomId>', 'tgt' => '<tgtAtomId>'], [], ..]
    }
}
