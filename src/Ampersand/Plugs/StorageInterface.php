<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs;

use Ampersand\Model;
use Ampersand\Transaction;

/**
 * Interface for storage implementations
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
interface StorageInterface
{
    
    public function getLabel();

    /**
     * This method is called during initialization of Ampersand app.
     *
     * Constructor of StorageInterface implementation MUST not throw Errors/Exceptions
     * when application is not installed (yet).
     */
    public function init(): void;

    public function startTransaction(Transaction $transaction): void;
    
    public function commitTransaction(Transaction $transaction): void;
    
    public function rollbackTransaction(Transaction $transaction): void;

    public function reinstallStorage(Model $model): void;

    public function addToModelVersionHistory(Model $model): void;

    public function getInstalledModelHash(): string;

    public function executeCustomSQLQuery(string $query): bool|array;
}
