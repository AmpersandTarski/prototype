<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Core;

enum TType: string
{
    case ALPHANUMERIC       = 'ALPHANUMERIC';
    case BIGALPHANUMERIC    = 'BIGALPHANUMERIC';
    case HUGEALPHANUMERIC   = 'HUGEALPHANUMERIC';
    case PASSWORD           = 'PASSWORD';
    case BINARY             = 'BINARY';
    case BIGBINARY          = 'BIGBINARY';
    case HUGEBINARY         = 'HUGEBINARY';
    case DATE               = 'DATE';
    case DATETIME           = 'DATETIME';
    case BOOLEAN            = 'BOOLEAN';
    case INTEGER            = 'INTEGER';
    case FLOAT              = 'FLOAT';
    case OBJECT             = 'OBJECT';
    case TYPEOFONE          = 'TYPEOFONE';

    public function getXmlTypeUri(): string
    {
        return match($this) {
            self::ALPHANUMERIC      => 'http://www.w3.org/2001/XMLSchema#string',
            self::BIGALPHANUMERIC   => 'http://www.w3.org/2001/XMLSchema#string',
            self::HUGEALPHANUMERIC  => 'http://www.w3.org/2001/XMLSchema#string',
            self::PASSWORD          => 'http://www.w3.org/2001/XMLSchema#string',
            self::BINARY            => 'http://www.w3.org/2001/XMLSchema#base64Binary',
            self::BIGBINARY         => 'http://www.w3.org/2001/XMLSchema#base64Binary',
            self::HUGEBINARY        => 'http://www.w3.org/2001/XMLSchema#base64Binary',
            self::DATE              => 'http://www.w3.org/2001/XMLSchema#data',
            self::DATETIME          => 'http://www.w3.org/2001/XMLSchema#dateType',
            self::BOOLEAN           => 'http://www.w3.org/2001/XMLSchema#boolean',
            self::INTEGER           => 'http://www.w3.org/2001/XMLSchema#integer',
            self::FLOAT             => 'http://www.w3.org/2001/XMLSchema#float',
            self::OBJECT            => 'http://www.w3.org/2001/XMLSchema#string',
            self::TYPEOFONE         => 'http://www.w3.org/2001/XMLSchema#string'
        };
    }
}
