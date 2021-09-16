<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs\MysqlDB;

use DateTime;
use Exception;
use DateTimeZone;
use Ampersand\Model;
use Ampersand\Core\Atom;
use Ampersand\Core\Link;
use Ampersand\Core\Concept;
use Ampersand\Core\Relation;
use Ampersand\Interfacing\ViewSegment;
use Ampersand\Interfacing\InterfaceExprObject;
use Ampersand\Plugs\ConceptPlugInterface;
use Ampersand\Plugs\IfcPlugInterface;
use Ampersand\Plugs\RelationPlugInterface;
use Ampersand\Plugs\ViewPlugInterface;
use Ampersand\Transaction;
use Psr\Log\LoggerInterface;
use Ampersand\Exception\NotInstalledException;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class MysqlDB implements ConceptPlugInterface, RelationPlugInterface, IfcPlugInterface, ViewPlugInterface
{
    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    
    /**
     * A connection to the mysql database
     *
     * @var \mysqli
     */
    protected $dbLink;
    
    /**
     * Host/server of mysql database
     *
     * @var string
     */
    protected $dbHost;
    
    /**
     * Username for mysql database
     *
     * @var string
     */
    protected $dbUser;
    
    /**
     * Password for mysql database
     *
     * @var string
     */
    protected $dbPass;
    
    /**
     * Database name
     *
     * @var string
     */
    protected $dbName;
    
    /**
     * Specifies if database transaction is active
     *
     * @var bool
     */
    protected $dbTransactionActive = false;

    /**
     * Specifies if this object is in debug mode
     * Affects the database exception-/error messages that are returned for errors in queries
     *
     * @var bool
     */
    protected $debugMode = false;

    /**
     * Specifies if this object is in production mode
     * Affects the database exception-/error messages that are returned for errors in schema constructs (database, tables, columns, etc)
     */
    protected $productionMode = false;

    /**
     * Contains the last executed query
     *
     * @var $string
     */
    protected $lastQuery = null;

    /**
     * Number of queries executed within a transaction
     *
     * Attribute is reset to 0 on start of (new) transaction
     *
     * @var integer
     */
    protected $queryCount = 0;
    
    /**
     * Constructor
     *
     * Constructor of StorageInterface implementation MUST not throw Errors/Exceptions
     * when application is not installed (yet).
     *
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPass
     * @param string $dbName
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(string $dbHost, string $dbUser, string $dbPass, string $dbName, LoggerInterface $logger, bool $debugMode = false, bool $productionMode = false)
    {
        $this->logger = $logger;

        $this->dbHost = $dbHost;
        $this->dbUser = $dbUser;
        $this->dbPass = $dbPass;
        $this->dbName = $dbName;

        $this->debugMode = $debugMode;
        $this->productionMode = $productionMode;
        
        try {
            // Enable mysqli errors to be thrown as Exceptions
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            // Connect to MYSQL database
            $this->dbLink = mysqli_init();
            
            // Flag MYSQLI_CLIENT_FOUND_ROWS -> https://www.codepuppet.com/2014/02/16/mysql-affected-rows-vs-rows-matched/
            $this->dbLink->real_connect($this->dbHost, $this->dbUser, $this->dbPass, '', 0, '', MYSQLI_CLIENT_FOUND_ROWS);
            $this->dbLink->set_charset("utf8mb4");
            
            // Set sql_mode to ANSI
            $this->dbLink->query("SET SESSION sql_mode = 'ANSI,TRADITIONAL'");
        } catch (Exception $e) {
            // Convert mysqli_sql_exceptions into 500 errors
            throw new Exception("Cannot connect to the database", 500);
        }
    }

    public function init()
    {
        $this->selectDB();
    }

    protected function selectDB()
    {
        try {
            $this->dbLink->select_db($this->dbName);
        } catch (Exception $e) {
            if (!$this->productionMode) {
                switch ($e->getCode()) {
                    case 1049: // Error: 1049 SQLSTATE: 42000 (ER_BAD_DB_ERROR) --> Database ($this->dbName) does not (yet) exist
                        throw new NotInstalledException("Database {$this->dbName} does not exist");
                    default:
                        throw $e;
                }
            } else {
                throw new Exception("Cannot connect to the databse", 500);
            }
        }
    }
    
    /**
     * Function to create new database. Drops database (and loose all data) if already exists
     *
     * @return void
     */
    protected function createDB()
    {
        // Drop database
        $this->logger->info("Drop database if exists: '{$this->dbName}'");
        $this->doQuery("DROP DATABASE IF EXISTS {$this->dbName}");
        
        // Create new database
        $this->logger->info("Create new database: '{$this->dbName}'");
        $this->doQuery("CREATE DATABASE {$this->dbName} DEFAULT CHARACTER SET UTF8MB4 COLLATE UTF8MB4_NOPAD_BIN");

        $this->dbLink->select_db($this->dbName);
    }

    /**
     * The database is dropped, created again and all tables are created
     *
     * @param \Ampersand\Model $model
     * @return void
     */
    public function reinstallStorage(Model $model)
    {
        $this->createDB();
        $structure = file_get_contents($model->getFolder() . '/database.sql');
        $this->logger->info("Execute database structure queries");
        $this->doQuery($structure, true);

        // Perform static (i.e. project independent) database structure queries
        // e.g. adds table to cache conjunct violations
        $queries = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'DBStructureQueries.sql');
        $this->doQuery($queries, true);
    }
    
    /**
     * Return escaped mysql representation of Atom (identifier) according to Ampersand technical types (TTypes)
     *
     * @throws Exception when technical type is not (yet) supported
     * @return mixed
     */
    protected function getDBRepresentation(Atom $atom)
    {
        if (is_null($atom->getId())) {
            throw new Exception("Atom identifier MUST NOT be NULL", 500);
        }
        
        switch ($atom->concept->type) {
            case "ALPHANUMERIC":
            case "BIGALPHANUMERIC":
            case "HUGEALPHANUMERIC":
            case "PASSWORD":
            case "TYPEOFONE":
                return (string) $this->escape($atom->getId());
            case "BOOLEAN":
                // Booleans are stored as tinyint(1) in the database. False = 0, True = 1
                switch ($atom->getId()) {
                    case "True":
                        return 1;
                    case "False":
                        return 0;
                    default:
                        throw new Exception("Unsupported string boolean value '{$atom->getId()}'. Supported are 'True', 'False'", 500);
                }
                break;
            case "DATE":
                $datetime = new DateTime($atom->getId());
                return $datetime->format('Y-m-d'); // format to store in database
            case "DATETIME":
                // DateTime atom(s) may contain a timezone, otherwise UTC is asumed.
                $datetime = new DateTime($atom->getId());
                $datetime->setTimezone(new DateTimeZone('UTC')); // convert to UTC to store in database
                return $datetime->format('Y-m-d H:i:s'); // format to store in database (UTC)
            case "FLOAT":
                return (float) $atom->getId();
            case "INTEGER":
                return (int) $atom->getId();
            case "OBJECT":
                return $this->escape($atom->getId());
            default:
                throw new Exception("Unknown/unsupported representation type '{$atom->concept->type}' for concept '[{$atom->concept}]'", 501);
        }
    }
    
    /**
     * Execute query on database. Function replaces reserved words by their corresponding value (e.g. _SESSION)
     * @param string $query
     * @return boolean|array
     *
     * TODO:
     * Create private equivalent that is used by addAtom(), addLink(), deleteLink() and deleteAtom() functions, to perform any INSERT, UPDATE, DELETE
     * The public version should be allowed to only do SELECT queries.
     * This is needed to prevent Extensions or ExecEngine functions to go around the functions in this class that keep track of the affectedConjuncts.
     */
    public function execute($query)
    {
        $this->logger->debug($query);
        $result = $this->doQuery($query);

        if ($result === false) {
            return false;
        } elseif ($result === true) {
            return true;
        }
        
        $arr = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $arr[] = $row;
        }
        return $arr;
    }

    public function prepare(string $query): \mysqli_stmt
    {
        $statement = $this->dbLink->prepare($query);

        if ($statement === false) {
            throw new Exception("Incorrect prepared statement in query: {$query}", 500);
        }

        return $statement;
    }
    
    /**
     * Execute query on database.
     *
     * @param string $query
     * @param bool $multiQuery specifies if query is a single command or multiple commands concatenated by a semicolon
     * @return mixed
     * @throws Exception
     */
    protected function doQuery($query, $multiQuery = false)
    {
        $this->lastQuery = $query;
        try {
            $this->queryCount++; // multi queries are counted as 1
            
            if ($multiQuery) {
                // MYSQLI_REPORT_STRICT mode doesn't throw Exceptions for multi_query execution,
                // therefore we throw exceptions

                $i = 1;
                // Execute multi query
                if ($this->dbLink->multi_query($query) === false) { // when first query errors
                    throw new Exception("Error in query {$i}: {$this->dbLink->error}", 500);
                }

                // Flush results, otherwise a connection stays open
                do {
                    $i++;
                    if ($res = $this->dbLink->store_result()) {
                        $res->free();
                    }
                } while ($this->dbLink->more_results() && $next = $this->dbLink->next_result());

                // Return result
                if ($next === false) { // when subsequent query errors
                    throw new Exception("Error in query {$i}: {$this->dbLink->error}", 500);
                } else {
                    return true;
                }
            } else {
                // MYSQLI_REPORT_STRICT mode automatically throws Exceptions for query execution
                return $this->dbLink->query($query);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            if (!$this->productionMode) {
                // Convert mysqli_sql_exceptions into 500 errors
                switch ($e->getCode()) {
                    case 1102: // Error: 1102 Incorrect database name
                        throw new Exception("Incorrect MYSQL database name '{$this->dbName}'. Configure a database name that complies with the MariaDB identifier rules. If not configured the Ampersand CONTEXT name is used by default", 500, $e);
                    case 1146: // Error: 1146 SQLSTATE: 42S02 (ER_NO_SUCH_TABLE)
                    case 1054: // Error: 1054 SQLSTATE: 42S22 (ER_BAD_FIELD_ERROR)
                        throw new NotInstalledException("{$e->getMessage()}. Try reinstalling the application");
                    case 1406: // Error: 1406 Data too long
                        throw new Exception("Data entry is too long ", 400);
                    default:
                        throw new Exception("MYSQL error " . $e->getCode() . ": " . $e->getMessage() . " in query:" . $query, 500);
                }
            } else {
                switch ($e->getCode()) {
                    case 1406: // Error: 1406 Data too long
                        throw new Exception("Data entry is too long", 400);
                    default:
                        throw new Exception("Error in database query", 500);
                }
            }
        }
    }
    
    /**
     * Escape identifier for use in database queries
     *
     * @param string $escapestr
     * @return NULL|string
     *
     * http://php.net/manual/en/language.types.string.php#language.types.string.parsing
     * http://php.net/manual/en/mysqli.real-escape-string.php
     */
    public function escape($escapestr)
    {
        if (is_null($escapestr)) {
            return null;
        } else {
            return $this->dbLink->real_escape_string($escapestr);
        }
    }

/**************************************************************************************************
 *
 * Implementation of StorageInterface methods (incl. database transaction handling)
 *
 *************************************************************************************************/
    
    /**
     * Returns name of storage implementation
     * @return string
     */
    public function getLabel()
    {
        return "MySQL database {$this->dbHost} - {$this->dbName}";
    }
    
    /**
     * Function to start/open a database transaction to track of all changes and be able to rollback
     *
     * @param \Ampersand\Transaction $transaction
     * @return void
     */
    public function startTransaction(Transaction $transaction)
    {
        if (!$this->dbTransactionActive) {
            $this->logger->info("Start mysql database transaction for {$transaction}");
            $this->queryCount = 0;
            $this->execute("START TRANSACTION");
            $this->dbTransactionActive = true; // set flag dbTransactionActive
        }
    }
    
    /**
     * Function to commit the open database transaction
     *
     * @param \Ampersand\Transaction $transaction
     * @return void
     */
    public function commitTransaction(Transaction $transaction)
    {
        $this->logger->info("Commit mysql database transaction for {$transaction}");
        $this->execute("COMMIT");
        $this->dbTransactionActive = false;
        $this->logger->info("{$this->queryCount} queries executed in this transaction");
    }
    
    /**
     * Function to rollback changes made in the open database transaction
     *
     * @param \Ampersand\Transaction $transaction
     * @return void
     */
    public function rollbackTransaction(Transaction $transaction)
    {
        $this->logger->info("Rollback mysql database transaction for {$transaction}");
        $this->execute("ROLLBACK");
        $this->dbTransactionActive = false;
        $this->logger->info("{$this->queryCount} queries executed in this transaction");
    }
    
/**************************************************************************************************
 *
 * Implementation of ConceptPlugInterface methods
 *
 *************************************************************************************************/

    /**
    * Check if atom exists in database

    * @param \Ampersand\Core\Atom $atom
    * @return bool
    */
    public function atomExists(Atom $atom)
    {
        $tableInfo = $atom->concept->getConceptTableInfo();
        /** @var \Ampersand\Plugs\MysqlDB\MysqlDBTableCol $firstCol */
        $firstCol = current($tableInfo->getCols());
        $atomId = $this->getDBRepresentation($atom);
        
        $query = "SELECT \"{$firstCol->getName()}\" FROM \"{$tableInfo->getName()}\" WHERE \"{$firstCol->getName()}\" = '{$atomId}'";
        $result = $this->execute($query);
        
        if (empty($result)) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Get all atoms for given concept
     *
     * @param \Ampersand\Core\Concept $concept
     * @return \Ampersand\Core\Atom[]
     */
    public function getAllAtoms(Concept $concept)
    {
        $tableInfo = $concept->getConceptTableInfo();
        
        // Query all atoms in table
        if (isset($tableInfo->allAtomsQuery)) {
            $query = $tableInfo->allAtomsQuery;
        } else {
            /** @var \Ampersand\Plugs\MysqlDB\MysqlDBTableCol $firstCol */
            $firstCol = current($tableInfo->getCols()); // We can query an arbitrary concept col for checking the existence of an atom
            $query = "SELECT DISTINCT \"{$firstCol->getName()}\" as \"atomId\" FROM \"{$tableInfo->getName()}\" WHERE \"{$firstCol->getName()}\" IS NOT NULL";
        }
        
        $arr = [];
        foreach ((array)$this->execute($query) as $row) {
            $tgtAtom = new Atom($row['atomId'], $concept);
            $tgtAtom->setQueryData($row);
            $arr[] = $tgtAtom;
        }
        return $arr;
    }
    
    /**
     * Add atom to database
     *
     * @param \Ampersand\Core\Atom $atom
     * @return void
     */
    public function addAtom(Atom $atom)
    {
        $atomId = $this->getDBRepresentation($atom);
                        
        // Get table properties
        $conceptTableInfo = $atom->concept->getConceptTableInfo();
        $conceptTable = $conceptTableInfo->getName();
        $conceptCols = $conceptTableInfo->getCols(); // Concept are registered in multiple cols in case of specializations. We insert the new atom in every column.
        
        // Create query string: "<col1>", "<col2>", etc
        $allConceptCols = '"' . implode('", "', $conceptTableInfo->getColNames()) . '"';
        
        
        // Create query string: '<newAtom>', '<newAtom', etc
        $atomIdsArray = array_fill(0, count($conceptCols), $atomId);
        $allValues = "'".implode("', '", $atomIdsArray)."'";
        
        $str = '';
        foreach ($conceptCols as $col) {
            /** @var \Ampersand\Plugs\MysqlDB\MysqlDBTableCol $col */
            $str .= ", \"{$col->getName()}\" = '{$atomId}'";
        }
        $duplicateStatement = substr($str, 1);
        
        $this->execute("INSERT INTO \"$conceptTable\" ($allConceptCols) VALUES ($allValues)"
                  ." ON DUPLICATE KEY UPDATE $duplicateStatement");
        
        // Check if query resulted in an affected row
        $this->checkForAffectedRows();
    }
    
    /**
     * Removing an atom as member from a concept set.
     *
     * @param \Ampersand\Core\Atom $atom
     * @throws \Exception
     * @return void
     */
    public function removeAtom(Atom $atom)
    {
        $atomId = $this->getDBRepresentation($atom);
        
        // Get table and col for WHERE clause
        $conceptTable = $atom->concept->getConceptTableInfo();
        $conceptCol = $atom->concept->getConceptTableInfo()->getFirstCol();
        
        // Get cols for UPDATE clause
        $colNames = [];
        $colNames[] = $conceptCol->getName(); // also update the concept col itself
        foreach ($atom->concept->getSpecializations() as $specConcept) {
            $colNames[] = $specConcept->getConceptTableInfo()->getFirstCol()->getName();
        }
        
        // Create query string: "<col1>" = '<atom>', "<col2>" = '<atom>', etc
        $queryString = "\"" . implode("\" = NULL, \"", $colNames) . "\" = NULL";
        
        $this->execute("UPDATE \"{$conceptTable->getName()}\" SET $queryString WHERE \"{$conceptCol->getName()}\" = '{$atomId}'");
        
        // Check if query resulted in an affected row
        $this->checkForAffectedRows();
    }
    
    /**
     * Delete atom from concept table in the database
     *
     * @param \Ampersand\Core\Atom $atom
     * @return void
     */
    public function deleteAtom(Atom $atom)
    {
        $atomId = $this->getDBRepresentation($atom);
        
        // Delete atom from concept table
        $conceptTable = $atom->concept->getConceptTableInfo();
        $query = "DELETE FROM \"{$conceptTable->getName()}\" WHERE \"{$conceptTable->getFirstCol()->getName()}\" = '{$atomId}' LIMIT 1";
        $this->execute($query);
        
        // Check if query resulted in an affected row
        $this->checkForAffectedRows();
    }

    public function executeCustomSQLQuery(string $query)
    {
        return $this->execute($query);
    }
    
/**************************************************************************************************
 *
 * Implementation of RelationPlugInterface methods
 *
 *************************************************************************************************/
    
    /**
    * Check if link exists in database

    * @param \Ampersand\Core\Link $link
    * @return bool
    */
    public function linkExists(Link $link)
    {
        $relTable = $link->relation()->getMysqlTable();
        $srcAtomId = $this->getDBRepresentation($link->src());
        $tgtAtomId = $this->getDBRepresentation($link->tgt());
        
        $result = $this->execute("SELECT * FROM \"{$relTable->getName()}\" WHERE \"{$relTable->srcCol()->getName()}\" = '{$srcAtomId}' AND \"{$relTable->tgtCol()->getName()}\" = '{$tgtAtomId}'");
        
        if (empty($result)) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
    * Get all links given a relation

    * @param \Ampersand\Core\Relation $relation
    * @param \Ampersand\Core\Atom|null $srcAtom if specified get all links with $srcAtom as source
    * @param \Ampersand\Core\Atom|null $tgtAtom if specified get all links with $tgtAtom as tgt
    * @return \Ampersand\Core\Link[]
    */
    public function getAllLinks(Relation $relation, Atom $srcAtom = null, Atom $tgtAtom = null): array
    {
        $relTable = $relation->getMysqlTable();
        
        // Query all atoms in table
        $query = "SELECT \"{$relTable->srcCol()->getName()}\" as \"src\", \"{$relTable->tgtCol()->getName()}\" as \"tgt\" FROM \"{$relTable->getName()}\"";
        
        // Construct WHERE-clause if applicable
        if (isset($srcAtom)) {
            $query .= " WHERE \"{$relTable->srcCol()->getName()}\" = '{$this->getDBRepresentation($srcAtom)}'";
        } else {
            $query .= " WHERE \"{$relTable->srcCol()->getName()}\" IS NOT NULL";
        }

        if (isset($tgtAtom)) {
            $query .= " AND \"{$relTable->tgtCol()->getName()}\" = '{$this->getDBRepresentation($tgtAtom)}'";
        } else {
            $query .= " AND \"{$relTable->tgtCol()->getName()}\" IS NOT NULL";
        }
        
        $links = [];
        foreach ((array)$this->execute($query) as $row) {
            $links[] = new Link($relation, new Atom($row['src'], $relation->srcConcept), new Atom($row['tgt'], $relation->tgtConcept));
        }
        
        return $links;
    }
    
    /**
     * Add link (srcAtom,tgtAtom) into database table for relation r
     *
     * @param \Ampersand\Core\Link $link
     * @return void
     */
    public function addLink(Link $link)
    {
        $relation = $link->relation();
        $srcAtomId = $this->getDBRepresentation($link->src());
        $tgtAtomId = $this->getDBRepresentation($link->tgt());
        
        $relTable = $relation->getMysqlTable();
        $table = $relTable->getName();
        $srcCol = $relTable->srcCol()->getName();
        $tgtCol = $relTable->tgtCol()->getName();
        
        switch ($relTable->inTableOf()) {
            case null: // Relation is administrated in n-n table
                $this->execute("REPLACE INTO \"{$table}\" (\"{$srcCol}\", \"{$tgtCol}\") VALUES ('{$srcAtomId}', '{$tgtAtomId}')");
                break;
            case 'src': // Relation is administrated in concept table (wide) of source of relation
                $this->execute("UPDATE \"{$table}\" SET \"{$tgtCol}\" = '{$tgtAtomId}' WHERE \"{$srcCol}\" = '{$srcAtomId}'");
                break;
            case 'tgt': //  Relation is administrated in concept table (wide) of target of relation
                $this->execute("UPDATE \"{$table}\" SET \"{$srcCol}\" = '{$srcAtomId}' WHERE \"{$tgtCol}\" = '{$tgtAtomId}'");
                break;
            default:
                throw new Exception("Unknown 'tableOf' option for relation '{$relation}'", 500);
        }
        
        // Check if query resulted in an affected row
        $this->checkForAffectedRows();
    }
    
    /**
     * Delete link (srcAtom,tgtAtom) into database table for relation r
     *
     * @param \Ampersand\Core\Link $link
     * @return void
     */
    public function deleteLink(Link $link)
    {
        $relation = $link->relation();
        $srcAtomId = $this->getDBRepresentation($link->src());
        $tgtAtomId = $this->getDBRepresentation($link->tgt());
         
        $relTable = $relation->getMysqlTable();
        $table = $relTable->getName();
        $srcCol = $relTable->srcCol()->getName();
        $tgtCol = $relTable->tgtCol()->getName();
         
        switch ($relTable->inTableOf()) {
            case null: // Relation is administrated in n-n table
                $this->execute("DELETE FROM \"{$table}\" WHERE \"{$srcCol}\" = '{$srcAtomId}' AND \"{$tgtCol}\" = '{$tgtAtomId}'");
                break;
            case 'src': // Relation is administrated in concept table (wide) of source of relation
                if (!$relTable->tgtCol()->nullAllowed()) {
                    throw new Exception("Cannot delete link {$link} because target column '{$tgtCol}' in table '{$table}' may not be set to null", 500);
                }
                // Source atom can be used in WHERE statement
                $this->execute("UPDATE \"{$table}\" SET \"{$tgtCol}\" = NULL WHERE \"{$srcCol}\" = '{$srcAtomId}'");
                break;
            case 'tgt': //  Relation is administrated in concept table (wide) of target of relation
                if (!$relTable->srcCol()->nullAllowed()) {
                    throw new Exception("Cannot delete link {$link} because source column '{$srcCol}' in table '{$table}' may not be set to null", 500);
                }
                // Target atom can be used in WHERE statement
                $this->execute("UPDATE \"{$table}\" SET \"{$srcCol}\" = NULL WHERE \"{$tgtCol}\" = '{$tgtAtomId}'");
                break;
            default:
                throw new Exception("Unknown 'tableOf' option for relation '{$relation}'", 500);
        }
        
        $this->checkForAffectedRows(); // Check if query resulted in an affected row
    }
    
    /**
     *
     *
     * @param \Ampersand\Core\Relation $relation relation from which to delete all links
     * @param \Ampersand\Core\Atom $atom atom for which to delete all links
     * @param string $srcOrTgt specifies to delete all link with $atom as src or tgt
     * @return void
     */
    public function deleteAllLinks(Relation $relation, Atom $atom, string $srcOrTgt): void
    {
        $relationTable = $relation->getMysqlTable();
        $atomId = $this->getDBRepresentation($atom);
        
        switch ($srcOrTgt) {
            case 'src':
                $whereCol = $relationTable->srcCol();
                break;
            case 'tgt':
                $whereCol = $relationTable->tgtCol();
                break;
            default:
                throw new Exception("Unknown/unsupported param option '{$srcOrTgt}'. Supported options are 'src' or 'tgt'", 500);
                break;
        }

        switch ($relationTable->inTableOf()) {
            case null: // n-n table -> remove entire row
                $query = "DELETE FROM \"{$relationTable->getName()}\" WHERE \"{$whereCol->getName()}\" = '{$atomId}'";
                break;
            case 'src': // administrated in table of src
                $setCol = $relationTable->tgtCol();
                $query = "UPDATE \"{$relationTable->getName()}\" SET \"{$setCol->getName()}\" = NULL WHERE \"{$whereCol->getName()}\" = '{$atomId}'";
                break;
            case 'tgt': // adminsitrated in table of tgt
                $setCol = $relationTable->srcCol();
                $query = "UPDATE \"{$relationTable->getName()}\" SET \"{$setCol->getName()}\" = NULL WHERE \"{$whereCol->getName()}\" = '{$atomId}'";
                break;
            default:
                throw new Exception("Unknown 'tableOf' option for relation '{$relation}'", 500);
        }
        
        $this->execute($query);
    }

    /**
     * Delete all links in a relation
     *
     * @param \Ampersand\Core\Relation $relation
     * @return void
     */
    public function emptyRelation(Relation $relation): void
    {
        $relationTable = $relation->getMysqlTable();

        switch ($relationTable->inTableOf()) {
            case null: // If n-n table, remove all rows
                $this->execute("DELETE FROM \"{$relationTable->getName()}\"");
                break;
            case 'src': // If in table of src concept, set tgt col to null
                $this->execute("UPDATE \"{$relationTable->getName()}\" SET \"{$relationTable->tgtCol()->getName()}\" = NULL");
                break;
            case 'tgt': // If in table of tgt concept, set src col to null
                $this->execute("UPDATE \"{$relationTable->getName()}\" SET \"{$relationTable->srcCol()->getName()}\" = NULL");
                break;
            default:
                throw new Exception("Unknown 'tableOf' option for relation '{$relation}'", 500);
        }
    }
    
/**************************************************************************************************
 *
 * Implementation of PlugInterface methods
 *
 *************************************************************************************************/
    
    /**
     * Execute query for given interface expression and source atom
     *
     * @param \Ampersand\Interfacing\InterfaceExprObject $ifc
     * @param \Ampersand\Core\Atom $srcAtom
     * @return mixed
     */
    public function executeIfcExpression(InterfaceExprObject $ifc, Atom $srcAtom)
    {
        $srcAtomId = $this->getDBRepresentation($srcAtom);
        $query = $ifc->getQuery();

        if (strpos($query, '_SRCATOM') !== false) {
            $query = str_replace('_SRCATOM', $srcAtomId, $query);
        } else {
            $query = "SELECT DISTINCT * FROM ({$query}) AS \"results\" WHERE \"src\" = '{$srcAtomId}' AND \"tgt\" IS NOT NULL";
        }
        return $this->execute($query);
    }
    
    /**
     * Execute query for giver view segement and source atom
     *
     * @param \Ampersand\Interfacing\ViewSegment $view
     * @param \Ampersand\Core\Atom $srcAtom
     * @return array
     */
    public function executeViewExpression(ViewSegment $view, Atom $srcAtom): array
    {
        $srcAtomId = $this->getDBRepresentation($srcAtom);
        $viewSQL = $view->getQuery();
        
        if (strpos($viewSQL, '_SRCATOM') !== false) {
            $query = str_replace('_SRCATOM', $srcAtomId, $viewSQL);
        } else {
            $query = "SELECT DISTINCT \"tgt\" FROM ({$viewSQL}) AS \"results\" WHERE \"src\" = '{$srcAtomId}' AND \"tgt\" IS NOT NULL";
        }
        
        return array_column((array) $this->execute($query), 'tgt');
    }
    
/**************************************************************************************************
 *
 * Helper functions
 *
 *************************************************************************************************/
    
    /**
     * Check if insert/update/delete function resulted in updated record(s). If not, report warning (or throw exception) to indicate that something is going wrong
     *
     * @throws \Exception when no records are affected and application is not in production mode
     * @return void
     */
    protected function checkForAffectedRows()
    {
        if ($this->dbLink->affected_rows == 0) {
            if ($this->debugMode) {
                throw new Exception("Oops.. something went wrong. No records affected in database", 500);
            } else { // silent + warning in log
                $this->logger->warning("No recors affected with query '{$this->lastQuery}'");
            }
        }
    }
}
