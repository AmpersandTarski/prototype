<?php

namespace Ampersand\Event;

use Ampersand\Event\AbstractEvent;
use Ampersand\Interfacing\Resource;
use Ampersand\Transaction;

class ResourceEvent extends AbstractEvent
{
    public const
        PATCHED = 'ampersand.core.resource.patched';

    public function __construct(
        public Resource $resource,
        public array $patches,
        public Transaction $transaction
    ) {
    }

}
