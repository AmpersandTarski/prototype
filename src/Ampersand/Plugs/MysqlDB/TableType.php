<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs\MysqlDB;

use Exception;

enum TableType: string
{
    case Src = 'src';
    case Tgt = 'tgt';
    case Binary = 'binary';

    public static function fromCompiler(string $tableOf, string $relationName): self
    {
        switch ($tableOf) {
            case 'src':
                return self::Src;
            case 'tgt':
                return self::Tgt;
            case null:
                return self::Binary;
            default:
                throw new Exception("Unknown tableOf value '{$tableOf}' specified for relation table {$relationName}", 500);
        }
    }
}
