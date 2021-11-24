<?php

namespace Ampersand\Event;

use Ampersand\Core\Atom;
use Ampersand\Event\AbstractEvent;
use Ampersand\Transaction;

class AtomEvent extends AbstractEvent
{
    public const
        ADDED = 'ampersand.core.atom.added',
        DELETED = 'ampersand.core.atom.deleted';

    protected Atom $atom;
    protected Transaction $transaction;

    public function __construct(Atom $atom, Transaction $transaction)
    {
        $this->atom = $atom;
        $this->transaction = $transaction;
    }

    public function getAtom(): Atom
    {
        return $this->atom;
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
