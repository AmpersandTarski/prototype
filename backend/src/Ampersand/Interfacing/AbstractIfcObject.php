<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Options;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
abstract class AbstractIfcObject implements InterfaceObjectInterface
{
    /**
     * Return list of all sub interface objects recursively (incl. the current object itself)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getIfcObjFlattened(): array
    {
        $arr = [$this];
        foreach ($this->getSubinterfaces(Options::DEFAULT_OPTIONS & ~Options::INCLUDE_REF_IFCS) as $subObj) {
            $arr = array_merge($arr, $subObj->getIfcObjFlattened());
        }
        return $arr;
    }
}
