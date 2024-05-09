<?php

namespace Ampersand\Event;

use Ampersand\Core\Link;
use Ampersand\Event\AbstractEvent;
use Ampersand\Transaction;

class LinkEvent extends AbstractEvent
{
    public const
        ADDED = 'ampersand.core.link.added',
        DELETED = 'ampersand.core.link.deleted';

    protected Link $link;
    protected Transaction $transaction;

    public function __construct(Link $link, Transaction $transaction)
    {
        $this->link = $link;
        $this->transaction = $transaction;
    }

    public function getLink(): Link
    {
        return $this->link;
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function isCommitted(): bool
    {
        return $this->transaction->isCommitted();
    }
}
