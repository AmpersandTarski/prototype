<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Exception;
use Ampersand\Interfacing\InterfaceExprObject;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\InterfaceNullObject;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceObjectFactory
{
    public static function getNullObject(): InterfaceObjectInterface
    {
        static $ifc = null;

        return $ifc ?? $ifc = new InterfaceNullObject();
    }
}
