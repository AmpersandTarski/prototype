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
use Ampersand\Interfacing\InterfaceTxtObject;
use Ampersand\Plugs\IfcPlugInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceObjectFactory
{
    public static function newObject(array $objectDef, IfcPlugInterface $defaultPlug, InterfaceObjectInterface $parent = null): InterfaceObjectInterface
    {
        switch ($objectDef['type']) {
            case 'ObjExpression':
                return new InterfaceExprObject($objectDef, $defaultPlug, $parent);
                break;
            case 'ObjText':
                return new InterfaceTxtObject($objectDef, $parent);
                break;
            default:
                throw new Exception("Unsupported/unknown InterfaceObject type specified: '{$objectDef['type']}' is not supported", 500);
                break;
        }
    }

    public static function newExprObject(array $objectDef, IfcPlugInterface $defaultPlug, InterfaceObjectInterface $parent = null): InterfaceExprObject
    {
        if ($objectDef['type'] !== 'ObjExpression') {
            throw new Exception("Interface expression object definition required, but '{$objectDef['type']}' provided.", 500);
        }

        return self::newObject($objectDef, $defaultPlug, $parent);
    }
    
    public static function getNullObject(string $resourceType): InterfaceObjectInterface
    {
        return new InterfaceNullObject($resourceType);
    }
}
