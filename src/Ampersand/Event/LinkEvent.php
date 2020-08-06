<?php

namespace Ampersand\Event;

use Ampersand\Core\Link;
use Ampersand\Event\AbstractEvent;

class LinkEvent extends AbstractEvent
{
    public const
        ADDED = 'ampersand.core.link.added',
        DELETED = 'ampersand.core.link.deleted';

    protected Link $link;

    public function __construct(Link $link)
    {
        $this->link = $link;
    }

    public function getLink(): Link
    {
        return $this->link;
    }
}
