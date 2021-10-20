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
     *
     * @return void
     */
    public function init();

    public function startTransaction(Transaction $transaction);
    
    public function commitTransaction(Transaction $transaction);
    
    public function rollbackTransaction(Transaction $transaction);

    public function reinstallStorage(Model $model);

    public function addToModelVersionHistory(Model $model);

    public function getInstalledModelHash(): string;

    public function executeCustomSQLQuery(string $query);
}
