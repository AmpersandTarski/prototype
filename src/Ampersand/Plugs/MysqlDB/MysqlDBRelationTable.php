<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs\MysqlDB;

use Exception;
use Ampersand\Plugs\MysqlDB\MysqlDBTableCol;
use Ampersand\Plugs\MysqlDB\TableType;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */

class MysqlDBRelationTable extends MysqlDBTable
{
    protected ?MysqlDBTableCol $srcCol = null;
    protected ?MysqlDBTableCol $tgtCol = null;

    /**
     * Specifies if this relation is administrated in the table of the src concept, the tgt concept or its own binary table
     */
    protected TableType $tableOf;

    /**
     * Constructor of RelationTable
     */
    public function __construct(string $name, TableType $tableOf = TableType::Binary)
    {
        parent::__construct($name);
        $this->tableOf = $tableOf;
    }

    public function inTableOf(): TableType
    {
        return $this->tableOf;
    }

    public function addSrcCol(MysqlDBTableCol $col): void
    {
        $this->srcCol = $col;
        $this->cols[$col->getName()] = $col;
    }

    public function addTgtCol(MysqlDBTableCol $col): void
    {
        $this->tgtCol = $col;
        $this->cols[$col->getName()] = $col;
    }

    public function srcCol(): MysqlDBTableCol
    {
        if (is_null($this->srcCol)) {
            throw new Exception("Src column for RelationTable {$this->name} not defined", 500);
        }
        return $this->srcCol;
    }

    public function tgtCol(): MysqlDBTableCol
    {
        if (is_null($this->tgtCol)) {
            throw new Exception("Tgt column for RelationTable {$this->name} not defined", 500);
        }
        return $this->tgtCol;
    }
}
