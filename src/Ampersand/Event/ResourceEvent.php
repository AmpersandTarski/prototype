<?php

namespace Ampersand\Event;

use Ampersand\Event\AbstractEvent;
use Ampersand\Interfacing\Resource;
use Ampersand\Transaction;

class ResourceEvent extends AbstractEvent
{
    public const
        POSTED  = 'ampersand.core.resource.post',
        PUT     = 'ampersand.core.resource.put',
        PATCHED = 'ampersand.core.resource.patched',
        DELETED = 'ampersand.core.resource.deleted';

    public function __construct(
        public Resource $resource,
        public Transaction $transaction,
        public ?mixed $body = null,
    ) {
    }

}
