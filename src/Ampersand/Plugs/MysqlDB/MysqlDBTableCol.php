<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs\MysqlDB;

use Exception;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class MysqlDBTableCol
{
    /**
     * Name/header of database column
     *
     * @var string
     */
    protected $name;

    /**
     * Specifies if value in this database column can be NULL
     *
     * @var bool
     */
    protected $null;

    /**
     * Specifies if this database column has uniquness constraint (i.e. no duplicates may exist in all rows)
     * @var bool
     */
    protected $unique;

    /**
     * Constructor
     */
    public function __construct(string $name, bool $null = false, bool $unique = true)
    {
        if ($name === '') {
            throw new Exception("Database table column name is an empty string", 500);
        }
        $this->name = $name;
        $this->null = $null;
        $this->unique = $unique;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function nullAllowed(): bool
    {
        return $this->null;
    }
}
