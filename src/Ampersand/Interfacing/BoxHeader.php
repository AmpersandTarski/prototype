<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

class BoxHeader
{
    /**
     * Specifies the type of the BOX (in case of BOX interface)
     * e.g. in ADL script: INTERFACE "test" : expr BOX <SCOLS> []
     * the type is 'SCOLS'
     * @var string
     */
    protected $type;

    protected $keyVals = [];

    public function __construct(string $type, array $keyVals)
    {
        $this->type = $type;
        $this->keyVals = $keyVals;
    }

    public function isSortable(): bool
    {
        return strtoupper(substr($this->type, 0, 1)) === 'S' || $this->hasKey('sortable');
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->keyVals);
    }
}
