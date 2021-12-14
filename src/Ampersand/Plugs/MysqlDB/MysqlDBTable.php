<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs\MysqlDB;

use Exception;
use Ampersand\Plugs\MysqlDB\MysqlDBTableCol;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class MysqlDBTable
{
    /**
     *
     * @var string
     */
    protected $name;

    /**
     *
     * @var array
     */
    protected $cols = [];
    
    /**
     *
     * @var string $allAtomsQuery
     */
    public $allAtomsQuery;

    public function __construct(string $name)
    {
        if ($name === '') {
            throw new Exception("Database table name is an empty string", 500);
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
     * @throws \Exception when no columns are defined for this table
     * @return \Ampersand\Plugs\MysqlDB\MysqlDBTableCol[]
     */
    public function getCols(): array
    {
        if (empty($this->cols)) {
            throw new Exception("No column defined for table '{$this->name}'", 500);
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
            throw new Exception("Col '{$colName}' does not exist in table '{$this->name}'", 500);
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
