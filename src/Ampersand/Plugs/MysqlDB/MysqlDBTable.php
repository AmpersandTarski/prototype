<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs\MysqlDB;

use Ampersand\Exception\FatalException;
use Ampersand\Exception\NotDefinedException;
use Ampersand\Plugs\MysqlDB\MysqlDBTableCol;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class MysqlDBTable
{
    protected string $name;

    protected array $cols = [];
    
    public string $allAtomsQuery;

    public function __construct(string $name)
    {
        if ($name === '') {
            throw new FatalException("Database table name is an empty string");
        }
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add database table column object to this table
     */
    public function addCol(MysqlDBTableCol $col): void
    {
        $this->cols[$col->getName()] = $col;
    }

    /**
     * Get all col objects for this table
     *
     * @throws \Ampersand\Exception\FatalException when no columns are defined for this table
     * @return \Ampersand\Plugs\MysqlDB\MysqlDBTableCol[]
     */
    public function getCols(): array
    {
        if (empty($this->cols)) {
            throw new FatalException("No column defined for table '{$this->name}'");
        }
        return $this->cols;
    }

    /**
     * Returns names of all table cols
     *
     * @return string[]
     */
    public function getColNames(): array
    {
        return array_keys($this->cols);
    }

    /**
     * Return col object with given column name
     */
    public function getCol(string $colName): MysqlDBTableCol
    {
        if (!array_key_exists($colName, $this->getCols())) {
            throw new NotDefinedException("Col '{$colName}' does not exist in table '{$this->name}'");
        }
        return $this->getCols()[$colName];
    }

    /**
     * Return first registered col object
     */
    public function getFirstCol(): MysqlDBTableCol
    {
        $cols = $this->getCols();
        return reset($cols);
    }
}
