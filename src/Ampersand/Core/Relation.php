<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Core;

use Exception;
use Ampersand\Plugs\MysqlDB\MysqlDBTableCol;
use Ampersand\Plugs\MysqlDB\MysqlDBRelationTable;
use Ampersand\Core\Concept;
use Ampersand\Rule\Conjunct;
use Ampersand\Plugs\RelationPlugInterface;
use Psr\Log\LoggerInterface;
use Ampersand\AmpersandApp;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Relation
{
    
    /**
     * Contains all relation definitions
     * @var Relation[]
     */
    private static $allRelations;
    
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to Ampersand app for which this relation is defined
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $app;
    
    /**
     * Dependency injection of plug implementation
     * There must at least be one plug for every relation
     *
     * @var \Ampersand\Plugs\RelationPlugInterface[]
     */
    protected $plugs = [];
    
    /**
     *
     * @var \Ampersand\Plugs\RelationPlugInterface
     */
    protected $primaryPlug;
    
    /**
     *
     * @var string
     */
    public $signature;
    
    /**
     *
     * @var string
     */
    public $name;
    
    /**
     *
     * @var Concept
     */
    public $srcConcept;
    
    /**
     *
     * @var Concept
     */
    public $tgtConcept;
    
    /**
     * @var boolean
     */
    public $isUni;
    
    /**
     *
     * @var boolean
     */
    public $isTot;
    
    /**
     *
     * @var boolean
     */
    public $isInj;
    
    /**
     *
     * @var boolean
     */
    public $isSur;
    
    /**
     *
     * @var boolean
     */
    public $isProp;
    
    /**
     * List of conjuncts that are affected by adding or removing a link in this relation
     *
     * @var \Ampersand\Rule\Conjunct[]
     */
    public $relatedConjuncts = [];
    
    /**
     *
     * @var \Ampersand\Plugs\MysqlDB\MysqlDBRelationTable
     */
    private $mysqlTable;
    
    /**
     * Relation constructor
     * Private function to prevent outside instantiation of Relations. Use Relation::getRelation($relationSignature)
     *
     * @param array $relationDef
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Ampersand\AmpersandApp $app
     */
    public function __construct($relationDef, LoggerInterface $logger, AmpersandApp $app)
    {
        $this->logger = $logger;
        $this->app = $app;

        $this->name = $relationDef['name'];
        $this->srcConcept = Concept::getConcept($relationDef['srcConceptId']);
        $this->tgtConcept = Concept::getConcept($relationDef['tgtConceptId']);
        
        $this->signature = $relationDef['signature'];
        
        $this->isUni = $relationDef['uni'];
        $this->isTot = $relationDef['tot'];
        $this->isInj = $relationDef['inj'];
        $this->isSur = $relationDef['sur'];
        $this->isProp = $relationDef['prop'];
        
        foreach ((array)$relationDef['affectedConjuncts'] as $conjId) {
            $conj = Conjunct::getConjunct($conjId);
            $this->relatedConjuncts[] = $conj;
        }

        // Specify mysql table information
        $this->mysqlTable = new MysqlDBRelationTable($relationDef['mysqlTable']['name'], $relationDef['mysqlTable']['tableOf']);
        
        $srcCol = $relationDef['mysqlTable']['srcCol'];
        $tgtCol = $relationDef['mysqlTable']['tgtCol'];
        
        $this->mysqlTable->addSrcCol(new MysqlDBTableCol($srcCol['name'], $srcCol['null'], $srcCol['unique']));
        $this->mysqlTable->addTgtCol(new MysqlDBTableCol($tgtCol['name'], $tgtCol['null'], $tgtCol['unique']));
    }
    
    /**
     * Function is called when object is treated as a string
     * @return string
     */
    public function __toString()
    {
        return $this->getSignature();
    }
    
    /**
     * Return signature of relation (format: relName[srcConceptName*tgtConceptName])
     * @return string
     */
    public function getSignature()
    {
        return "{$this->name}[{$this->srcConcept}*{$this->tgtConcept}]";
    }
    
    /**
     * Returns array with signal conjuncts that are affected by updating this Relation
     * @return \Ampersand\Rule\Conjunct[]
     */
    public function getRelatedConjuncts()
    {
        return $this->relatedConjuncts;
    }
    
    /**
     *
     * @return \Ampersand\Plugs\MysqlDB\MysqlDBRelationTable
     */
    public function getMysqlTable(): MysqlDBRelationTable
    {
        return $this->mysqlTable;
    }

    /**
     * Get registered plugs for this relation
     *
     * @return \Ampersand\Plugs\RelationPlugInterface[]
     */
    public function getPlugs()
    {
        if (empty($this->plugs)) {
            throw new Exception("No plug(s) provided for relation {$this->getSignature()}", 500);
        }
        return $this->plugs;
    }

    /**
     * Add plug for this relation
     *
     * @param \Ampersand\Plugs\RelationPlugInterface $plug
     * @return void
     */
    public function addPlug(RelationPlugInterface $plug)
    {
        if (!in_array($plug, $this->plugs)) {
            $this->plugs[] = $plug;
        }
        if (count($this->plugs) === 1) {
            $this->primaryPlug = $plug;
        }
    }
    
    /**
     * Check if link (tuple of src and tgt atom) exists in this relation
     * @param \Ampersand\Core\Link $link
     * @return boolean
     */
    public function linkExists(Link $link)
    {
        $this->logger->debug("Checking if link {$link} exists in plug");
        
        return $this->primaryPlug->linkExists($link);
    }
    
    /**
    * Get all links for this relation
    * @param \Ampersand\Core\Atom|null $srcAtom if specified get all links with $srcAtom as source
    * @param \Ampersand\Core\Atom|null $tgtAtom if specified get all links with $tgtAtom as tgt
    * @return \Ampersand\Core\Link[]
    */
    public function getAllLinks(Atom $srcAtom = null, Atom $tgtAtom = null)
    {
        return $this->primaryPlug->getAllLinks($this, $srcAtom, $tgtAtom);
    }
    
    /**
     * Add link to this relation
     * @param \Ampersand\Core\Link $link
     * @return void
     */
    public function addLink(Link $link)
    {
        $this->logger->debug("Add link {$link} to plug");
        $this->app->getCurrentTransaction()->addAffectedRelations($this); // Add relation to affected relations. Needed for conjunct evaluation and transaction management
        
        // Ensure that atoms exist in their concept tables
        $link->src()->add(); // TODO: remove when we know for sure that this is guaranteed by calling functions
        $link->tgt()->add(); // TODO: remove when we know for sure that this is guaranteed by calling functions
        
        foreach ($this->getPlugs() as $plug) {
            $plug->addLink($link);
        }
    }
    
    /**
     * Delete link from this relation
     * @param \Ampersand\Core\Link $link
     * @return void
     */
    public function deleteLink(Link $link)
    {
        $this->logger->debug("Delete link {$link} from plug");
        $this->app->getCurrentTransaction()->addAffectedRelations($this); // Add relation to affected relations. Needed for conjunct evaluation and transaction management
        
        foreach ($this->getPlugs() as $plug) {
            $plug->deleteLink($link);
        }
    }
    
    /**
     * @param \Ampersand\Core\Atom|null $atom atom for which to delete all links
     * @param string|null $srcOrTgt specifies to delete all link with $atom as src, tgt or both (null/not provided)
     * @return void
     */
    public function deleteAllLinks(Atom $atom = null, $srcOrTgt = null)
    {
        // Add relation to affected relations. Needed for conjunct evaluation and transaction management
        $this->app->getCurrentTransaction()->addAffectedRelations($this);
        
        // Checks and logging
        if (is_null($atom)) {
            $this->logger->debug("Deleting all links in relation {$this}");
        } else {
            switch ($srcOrTgt) {
                case 'src':
                case 'tgt':
                    $this->logger->debug("Deleting all links in relation {$this} with {$atom} set as {$srcOrTgt}");
                    break;
                case null:
                    $this->logger->debug("Deleting all links in relation {$this} with {$atom} set as src or tgt");
                    break;
                default:
                    throw new Exception("Unknown/unsupported param option '{$srcOrTgt}'. Supported options are 'src', 'tgt' or null", 500);
                    break;
            }
        }

        // Perform delete in all plugs
        foreach ($this->getPlugs() as $plug) {
            $plug->deleteAllLinks($this, $atom, $srcOrTgt);
        }
    }
    
    /**********************************************************************************************
     *
     * Static functions
     *
     *********************************************************************************************/
    
     /**
      * Delete all links where $atom is used
      *
      * @param \Ampersand\Core\Atom $atom
      * @return void
      */
    public static function deleteAllLinksWithAtom(Atom $atom)
    {
        foreach (self::getAllRelations() as $relation) {
            if ($atom->concept->inSameClassificationTree($relation->srcConcept)) {
                $relation->deleteAllLinks($atom, 'src');
            }
            if ($atom->concept->inSameClassificationTree($relation->tgtConcept)) {
                $relation->deleteAllLinks($atom, 'tgt');
            }
        }
    }
    
    /**
     * Delete all links where $atom is used as src or tgt atom
     * from relations where $atom's concept (or any of its specializations) is used as src or tgt concept
     *
     * @param \Ampersand\Core\Atom $atom
     * @return void
     */
    public static function deleteAllSpecializationLinks(Atom $atom)
    {
        foreach (self::getAllRelations() as $relation) {
            if ($atom->concept->hasSpecialization($relation->srcConcept, true)) {
                $relation->deleteAllLinks($atom, 'src');
            }
            if ($atom->concept->hasSpecialization($relation->tgtConcept, true)) {
                $relation->deleteAllLinks($atom, 'tgt');
            }
        }
    }
    
    /**
     * Return Relation object
     * @param string $relationSignature
     * @param \Ampersand\Core\Concept|null $srcConcept
     * @param \Ampersand\Core\Concept|null $tgtConcept
     * @throws Exception if Relation is not defined
     * @return \Ampersand\Core\Relation
     */
    public static function getRelation($relationSignature, Concept $srcConcept = null, Concept $tgtConcept = null)
    {
        $relations = self::getAllRelations();
        
        // If relation can be found by its fullRelationSignature return the relation
        if (array_key_exists($relationSignature, $relations)) {
            $relation = $relations[$relationSignature];
            
            // If srcConceptName and tgtConceptName are provided, check that they match the found relation
            if (!is_null($srcConcept) && !in_array($srcConcept, $relation->srcConcept->getSpecializationsIncl())) {
                throw new Exception("Provided src concept '{$srcConcept}' does not match the relation '{$relation}'", 500);
            }
            if (!is_null($tgtConcept) && !in_array($tgtConcept, $relation->tgtConcept->getSpecializationsIncl())) {
                throw new Exception("Provided tgt concept '{$tgtConcept}' does not match the relation '{$relation}'", 500);
            }
            
            return $relation;
        }
        
        // Else try to find the relation by its name, srcConcept and tgtConcept
        if (!is_null($srcConcept) && !is_null($tgtConcept)) {
            foreach ($relations as $relation) {
                if ($relation->name == $relationSignature
                        && in_array($srcConcept, $relation->srcConcept->getSpecializationsIncl())
                        && in_array($tgtConcept, $relation->tgtConcept->getSpecializationsIncl())
                  ) {
                    return $relation;
                }
            }
        }
        
        // Else
        throw new Exception("Relation '{$relationSignature}[{$srcConcept}*{$tgtConcept}]' is not defined", 500);
    }
    
    /**
     * Returns array with all Relation objects
     * @return \Ampersand\Core\Relation[]
     */
    public static function getAllRelations()
    {
        if (!isset(self::$allRelations)) {
            throw new Exception("Relation definitions not loaded yet", 500);
        }
         
        return self::$allRelations;
    }
    
    /**
     * Import all Relation definitions from json file and instantiate Relation objects
     *
     * @param string $fileName containing the Ampersand relation definitions
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public static function setAllRelations(string $fileName, LoggerInterface $logger, AmpersandApp $app)
    {
        self::$allRelations = [];
    
        // Import json file
        $allRelationDefs = (array)json_decode(file_get_contents($fileName), true);
    
        foreach ($allRelationDefs as $relationDef) {
            $relation = new Relation($relationDef, $logger, $app);
            self::$allRelations[$relation->signature] = $relation;
        }
    }
}
