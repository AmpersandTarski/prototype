<?php

namespace Ampersand\Event;

use Ampersand\Event\AbstractEvent;
use Ampersand\Transaction;

class TransactionEvent extends AbstractEvent
{
    public const
        STARTED = 'ampersand.transaction.started',
        COMMITTED = 'ampersand.transaction.committed',
        ROLLEDBACK = 'ampersand.transaction.rolledback';

    protected Transaction $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }
}
