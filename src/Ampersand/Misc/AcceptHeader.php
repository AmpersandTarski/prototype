<?php
/**
 * Note : Code is released under the GNU LGPL
 *
 * Please do not change the header of this file
 *
 * This library is free software; you can redistribute it and/or modify it under the terms of the GNU
 * Lesser General Public License as published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU Lesser General Public License for more details.
 *
 * Source: https://github.com/adoy/Accept-Header-Parser
 */

namespace Ampersand\Misc;

/**
 * The AcceptHeader page will parse and sort the different
 * allowed types for the content negociations
 *
 * @author      Pierrick Charron <pierrick@webstart.fr>
 */
class AcceptHeader extends \ArrayObject
{
    public function __construct(string $header)
    {
        $acceptedTypes = $this->parse($header);
        usort($acceptedTypes, array($this, 'compare'));
        parent::__construct($acceptedTypes);
    }

    /**
     * Parse the accept header and return an array containing all the informations about the Accepted types
     */
    private function parse(string $data): array
    {
        $array = array();
        $items = explode(',', $data);
        foreach ($items as $item) {
            $elems = explode(';', $item);
            $acceptElement = array();
            $mime = current($elems);
            list($type, $subtype) = explode('/', $mime);
            $acceptElement['type'] = trim($type);
            $acceptElement['subtype'] = trim($subtype);
            $acceptElement['raw'] = $mime;
            $acceptElement['params'] = array();
            while (next($elems)) {
                list($name, $value) = explode('=', current($elems));
                $acceptElement['params'][trim($name)] = trim($value);
            }
            $array[] = $acceptElement;
        }
        return $array;
    }

    /**
     * Compare two Accepted types with their parameters to know if one media type should be used instead of an other
     */
    private function compare(array $a, array $b): int
    {
        $a_q = isset($a['params']['q']) ? floatval($a['params']['q']) : 1.0;
        $b_q = isset($b['params']['q']) ? floatval($b['params']['q']) : 1.0;
        if ($a_q === $b_q) {
            $a_count = count($a['params']);
            $b_count = count($b['params']);
            if ($a_count === $b_count) {
                if ($r = $this->compareSubType($a['subtype'], $b['subtype'])) {
                    return $r;
                } else {
                    return $this->compareSubType($a['type'], $b['type']);
                }
            } else {
                return $a_count <=> $b_count;
            }
        } else {
            return $a_q <=> $b_q;
        }
    }

    /**
     * Compare two subtypes
     */
    private function compareSubType(string $a, string $b): int
    {
        if ($a === '*' && $b !== '*') {
            return 1;
        } elseif ($b === '*' && $a !== '*') {
            return -1;
        } else {
            return 0;
        }
    }
}
