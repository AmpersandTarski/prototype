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

class MysqlDBRelationTable extends MysqlDBTable
{

    /**
     *
     * @var \Ampersand\Plugs\MysqlDB\MysqlDBTableCol
     */
    protected $srcCol = null;

    /**
     *
     * @var \Ampersand\Plugs\MysqlDB\MysqlDBTableCol
     */
    protected $tgtCol = null;

    /**
     * Specifies if this relation is administrated in the table of the src concept ('src'), the tgt concept ('tgt') or its own n-n table (null)
     *
     * @var string
     */
    protected $tableOf;

    /**
     * Constructor of RelationTable
     *
     * @param string|null $tableOf ('src', 'tgt' or null)
     * TODO: use enum here
     */
    public function __construct(string $name, string $tableOf = null)
    {
        parent::__construct($name);

        switch ($tableOf) {
            case 'src':
            case 'tgt':
            case null:
                $this->tableOf = $tableOf;
                break;
            default:
                throw new Exception("Unknown tableOf value '{$tableOf}' specified for RelationTable {$this->name}", 500);
        }
    }

    public function inTableOf(): ?string
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
