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
        // sortByAndHide sorts on a column that is not rendered, so it needs the
        // sort values even when the box carries no 'sortable' annotation.
        return strtoupper(substr($this->type, 0, 1)) === 'S'
            || $this->hasKey('sortable')
            || $this->hasKey('sortByAndHide');
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->keyVals);
    }
}
