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
     *
     * E.g. in ADL script: `INTERFACE "test" : expr BOX <SCOLS> []` the type is 'SCOLS'
     */
    protected string $type;

    protected $keyVals = [];

    public function __construct(array $boxHeaderDef)
    {
        $this->type = $boxHeaderDef['type'];

        foreach ($boxHeaderDef['keyVals'] as $keyVal) {
            $this->keyVals[$keyVal['key']] = $keyVal['value']; // Unpack keyVals list
        }
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
