<?php

namespace Ampersand\Event;

use Ampersand\Core\Atom;
use Ampersand\Event\AbstractEvent;

class AtomEvent extends AbstractEvent
{
    public const
        ADDED = 'ampersand.core.atom.added',
        DELETED = 'ampersand.core.atom.deleted';

    protected Atom $atom;

    public function __construct(Atom $atom)
    {
        $this->atom = $atom;
    }

    public function getAtom(): Atom
    {
        return $this->atom;
    }
}
