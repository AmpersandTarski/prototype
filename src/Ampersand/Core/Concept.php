<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Core;

use Exception;
use Ampersand\Plugs\MysqlDB\MysqlDBTable;
use Ampersand\Plugs\MysqlDB\MysqlDBTableCol;
use Ampersand\Core\Atom;
use Ampersand\Plugs\ConceptPlugInterface;
use Psr\Log\LoggerInterface;
use Ampersand\AmpersandApp;
use Ampersand\Event\AtomEvent;
use Ramsey\Uuid\Uuid;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Concept
{
    protected static $representTypes =
        [ 'ALPHANUMERIC'        => ['datatype' => 'string',     'xml' => 'http://www.w3.org/2001/XMLSchema#string']
        , 'BIGALPHANUMERIC'     => ['datatype' => 'string',     'xml' => 'http://www.w3.org/2001/XMLSchema#string']
        , 'HUGEALPHANUMERIC'    => ['datatype' => 'string',     'xml' => 'http://www.w3.org/2001/XMLSchema#string']
        , 'PASSWORD'            => ['datatype' => 'string',     'xml' => 'http://www.w3.org/2001/XMLSchema#string']
        , 'BINARY'              => ['datatype' => 'binary',     'xml' => 'http://www.w3.org/2001/XMLSchema#base64Binary']
        , 'BIGBINARY'           => ['datatype' => 'binary',     'xml' => 'http://www.w3.org/2001/XMLSchema#base64Binary']
        , 'HUGEBINARY'          => ['datatype' => 'binary',     'xml' => 'http://www.w3.org/2001/XMLSchema#base64Binary']
        , 'DATE'                => ['datatype' => 'date',       'xml' => 'http://www.w3.org/2001/XMLSchema#data']
        , 'DATETIME'            => ['datatype' => 'datetime',   'xml' => 'http://www.w3.org/2001/XMLSchema#dateType']
        , 'BOOLEAN'             => ['datatype' => 'boolean',    'xml' => 'http://www.w3.org/2001/XMLSchema#boolean']
        , 'INTEGER'             => ['datatype' => 'integer',    'xml' => 'http://www.w3.org/2001/XMLSchema#integer']
        , 'FLOAT'               => ['datatype' => 'float',      'xml' => 'http://www.w3.org/2001/XMLSchema#float']
        , 'OBJECT'              => ['datatype' => 'object',     'xml' => 'http://www.w3.org/2001/XMLSchema#string']
        , 'TYPEOFONE'           => ['datatype' => 'string',     'xml' => 'http://www.w3.org/2001/XMLSchema#string']
        ];
    
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to Ampersand app for which this concept is defined
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $app;
    
    /**
     * Dependency injection of ConceptPlug implementation
     * There must at least be one plug for every concept
     *
     * @var \Ampersand\Plugs\ConceptPlugInterface[]
     */
    protected $plugs = [];
    
    /**
     *
     * @var \Ampersand\Plugs\ConceptPlugInterface
     */
    protected $primaryPlug;
    
    /**
     * Definition from which Concept object is created
     *
     * @var array
     */
    private $def;
    
    /**
     * Name (and unique escaped identifier) of concept as defined in Ampersand script
     * TODO: rename var to $id
     *
     * @var string
     */
    public $name;
    
    /**
     * Unescaped name of concept as defined in Ampersand script
     *
     * @var string
     */
    public $label;
    
    /**
     * Specifies technical representation of atoms of this concept (e.g. OBJECT, ALPHANUMERIC, INTERGER, BOOLEAN, etc)
     *
     * @var string
     */
    public $type;
    
    /**
     * List of conjuncts that are affected by creating or deleting an atom of this concept
     *
     * @var \Ampersand\Rule\Conjunct[]
     */
    protected $relatedConjuncts = [];
    
    /**
     * List of concepts (name) that are specializations of this concept
     *
     * @var string[]
     */
    private $specializations = [];
    
    /**
     * List of concepts (name) that are direct specializations of this concept
     *
     * @var string[]
     */
    private $directSpecs = [];
    
    /**
     * List of concepts (name) that are generalizations of this concept
     *
     * @var string[]
     */
    private $generalizations = [];
    
    /**
     * List of concepts (name) that are direct generalizations of this concept
     *
     * @var string[]
     */
    private $directGens = [];
    
    /**
     * Concept identifier of largest generalization for this concept
     *
     * @var string
     */
    private $largestConceptId;
    
    /**
     * List of interface identifiers that have this concept as src concept
     *
     * @var string[]
     */
    protected $interfaceIds = [];
    
    /**
     * Default view object for atoms of this concept
     *
     * @var \Ampersand\Interfacing\View|NULL
     */
    private $defaultView = null;

    /**
     * Contains information about mysql table and columns in which this concept is administrated
     *
     * @var \Ampersand\Plugs\MysqlDB\MysqlDBTable
     */
    private $mysqlConceptTable;
    
    /**
     * List with atom identifiers that exist in the concept
     * used to prevent unnecessary checks if atom exists in plug
     *
     * @var string[]
     */
    private $atomCache = [];
    
    /**
     * Concept constructor
     *
     * @param array $conceptDef
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Ampersand\AmpersandApp $app
     */
    public function __construct(array $conceptDef, LoggerInterface $logger, AmpersandApp $app)
    {
        $this->logger = $logger;
        $this->app = $app;
        
        $this->def = $conceptDef;
        
        $this->name = $conceptDef['id'];
        $this->label = $conceptDef['label'];
        $this->type = $conceptDef['type'];

        if (!array_key_exists($this->type, self::$representTypes)) {
            throw new Exception("Unsupported represent type: '{$this->type}'. Supported are: " . implode(',', array_keys(self::$representTypes)), 500);
        }
        
        foreach ((array)$conceptDef['affectedConjuncts'] as $conjId) {
            $conj = $app->getModel()->getConjunct($conjId);
            $this->relatedConjuncts[] = $conj;
        }
        
        $this->specializations = (array)$conceptDef['specializations'];
        $this->generalizations = (array)$conceptDef['generalizations'];
        $this->directSpecs = (array)$conceptDef['directSpecs'];
        $this->directGens = (array)$conceptDef['directGens'];
        $this->interfaceIds = (array)$conceptDef['interfaces'];
        $this->largestConceptId = $conceptDef['largestConcept'];
        
        if (!is_null($conceptDef['defaultViewId'])) {
            $this->defaultView = $app->getModel()->getView($conceptDef['defaultViewId']);
        }
        
        if (!is_null($conceptDef['conceptTable'])) {
            $this->mysqlConceptTable = new MysqlDBTable($conceptDef['conceptTable']['name']);
            foreach ($conceptDef['conceptTable']['cols'] as $colName) {
                $this->mysqlConceptTable->addCol(new MysqlDBTableCol($colName));
            }
        } else {
            throw new Exception("Concept table information not defined for concept {$this->label}", 500);
        }
    }

    /**
     * Temporary function to manually add a more optimize query for getting all atoms at once
     * Default view and query belong together
     * TODO: replace hack by proper implementation in Ampersand generator
     *
     * @param string $viewId
     * @param string $query
     * @return void
     */
    public function setAllAtomsQuery(string $viewId, string $query)
    {
        $this->defaultView = $this->app->getModel()->getView($viewId);
        $this->mysqlConceptTable->allAtomsQuery = $query;
    }
    
    /**
     * Function is called when object is treated as a string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->label;
    }

    /**
     * Get escaped name of concept
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getApp(): AmpersandApp
    {
        return $this->app;
    }

    /**
     * Undocumented function
     *
     * @param string $serialization options are: 'datatype', 'xml'
     * @return string
     */
    public function getDatatype($serialization = 'datatype'): string
    {
        return self::$representTypes[$this->type][$serialization];
    }
    
    /**
     * Specifies if concept representation is integer
     *
     * @return bool
     */
    public function isInteger(): bool
    {
        return $this->type === "INTEGER";
    }
    
    /**
     * Specifies if concept is object
     *
     * @return bool
     */
    public function isObject(): bool
    {
        return $this->type === "OBJECT";
    }
    
    /**
     * Check if concept is file object
     *
     * @return boolean
     */
    public function isFileObject(): bool
    {
        foreach ($this->getGeneralizationsIncl() as $concept) {
            if ($concept->label === 'FileObject') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Returns if concept is the ampersand SESSION concept
     *
     * @return bool
     */
    public function isSession(): bool
    {
        foreach ($this->getGeneralizationsIncl() as $concept) {
            if ($concept->label === 'SESSION') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if this concept is a generalization of another given concept
     *
     * @param \Ampersand\Core\Concept $concept
     * @param bool $thisIncluded specifies if $this concept is included in comparison
     * @return bool
     */
    public function hasSpecialization(Concept $concept, bool $thisIncluded = false): bool
    {
        if ($thisIncluded && $concept == $this) {
            return true;
        }
        
        return in_array($concept->name, $this->specializations);
    }
    
    /**
     * Check if this concept is a specialization of another given concept
     *
     * @param \Ampersand\Core\Concept $concept
     * @param bool $thisIncluded specifies if $this concept is included in comparison
     * @return bool
     */
    public function hasGeneralization(Concept $concept, bool $thisIncluded = false): bool
    {
        if ($thisIncluded && $concept == $this) {
            return true;
        }
        
        return in_array($concept->name, $this->generalizations);
    }
    
    /**
     * Checks if this concept is in same classification tree as the provided concept
     *
     * @param \Ampersand\Core\Concept $concept
     * @return bool
     */
    public function inSameClassificationTree(Concept $concept): bool
    {
        if ($this->hasSpecialization($concept, true)) {
            return true;
        }
        if ($this->hasGeneralization($concept, true)) {
            return true;
        }
         
        // else
        return false;
    }
    
    /**
     * Array of concepts of which this concept is a generalization.
     *
     * @param bool $onlyDirectSpecializations (default=false)
     * @return \Ampersand\Core\Concept[]
     */
    public function getSpecializations(bool $onlyDirectSpecializations = false)
    {
        $specizalizations = $onlyDirectSpecializations ? $this->directSpecs : $this->specializations;
        
        $returnArr = [];
        foreach ($specizalizations as $conceptName) {
            $returnArr[$conceptName] = $this->app->getModel()->getConcept($conceptName);
        }
        return $returnArr;
    }
    
    /**
     * Array of concepts of which this concept is a specialization (exluding the concept itself).
     *
     * @param bool $onlyDirectGeneralizations (default=false)
     * @return \Ampersand\Core\Concept[]
     */
    public function getGeneralizations(bool $onlyDirectGeneralizations = false)
    {
        $generalizations = $onlyDirectGeneralizations ? $this->directGens : $this->generalizations;

        $returnArr = [];
        foreach ($generalizations as $conceptName) {
            $returnArr[$conceptName] = $this->app->getModel()->getConcept($conceptName);
        }
        return $returnArr;
    }
    
    /**
     * Array of all concepts of which this concept is a generalization including the concept itself.
     *
     * @return \Ampersand\Core\Concept[]
     */
    public function getSpecializationsIncl()
    {
        $specializations = $this->getSpecializations();
        $specializations[] = $this;
        return $specializations;
    }
    
    /**
     * Array of all concepts of which this concept is a specialization including the concept itself.
     *
     * @return \Ampersand\Core\Concept[]
     */
    public function getGeneralizationsIncl()
    {
        $generalizations = $this->getGeneralizations();
        $generalizations[] = $this;
        return $generalizations;
    }
    
    /**
     * Returns largest generalization concept (can be itself)
     *
     * @return \Ampersand\Core\Concept
     */
    public function getLargestConcept()
    {
        return $this->app->getModel()->getConcept($this->largestConceptId);
    }
    
    /**
     * Returns array with signal conjuncts that are affected by creating or deleting an atom of this concept
     *
     * @return \Ampersand\Rule\Conjunct[]
     */
    public function getRelatedConjuncts()
    {
        return $this->relatedConjuncts;
    }
    
    /**
     * Returns database table info for concept
     *
     * @throws \Exception if no database table is defined
     * @return \Ampersand\Plugs\MysqlDB\MysqlDBTable
     */
    public function getConceptTableInfo()
    {
        return $this->mysqlConceptTable;
    }

    /**
     * Get registered plugs for this concept
     *
     * @return \Ampersand\Plugs\ConceptPlugInterface[]
     */
    public function getPlugs()
    {
        if (empty($this->plugs)) {
            throw new Exception("No plug(s) provided for concept {$this}", 500);
        }
        return $this->plugs;
    }

    /**
     * Add plug for this concept
     *
     * @param \Ampersand\Plugs\ConceptPlugInterface $plug
     * @return void
     */
    public function addPlug(ConceptPlugInterface $plug)
    {
        if (!in_array($plug, $this->plugs)) {
            $this->plugs[] = $plug;
        }
        if (count($this->plugs) === 1) {
            $this->primaryPlug = $plug;
        }
    }

    /**
     * Clear atom cache
     *
     * @return void
     */
    public function clearAtomCache()
    {
        $this->atomCache = [];
    }
    
    /**
     * Generate a new atom identifier for this concept
     * @return string
     */
    public function createNewAtomId(): string
    {
        // TODO: remove this hack with _AI (autoincrement feature)
        if (strpos($this->name, '_AI') !== false && $this->isInteger()) {
            /** @var \Ampersand\Plugs\MysqlDB\MysqlDBTableCol $firstCol */
            $firstCol = current($this->mysqlConceptTable->getCols());
            $query = "SELECT MAX(\"{$firstCol->getName()}\") as \"MAX\" FROM \"{$this->mysqlConceptTable->getName()}\"";
             
            $result = array_column((array)$this->primaryPlug->executeCustomSQLQuery($query), 'MAX');
    
            if (empty($result)) {
                return (string) 1;
            } else {
                return (string) ($result[0] + 1);
            }
        }

        return $this->name . '_' . Uuid::uuid4()->toString();
    }
    
    /**
     * Instantiate new Atom object in backend
     * NB! this does not result automatically in a database insert
     *
     * @return \Ampersand\Core\Atom
     */
    public function createNewAtom(): Atom
    {
        return new Atom($this->createNewAtomId(), $this);
    }
    
    /**
     * Check if atom exists
     *
     * @param \Ampersand\Core\Atom $atom
     * @return bool
     */
    public function atomExists(Atom $atom): bool
    {
        // Convert atom to more generic/specific concept if needed
        if ($atom->concept !== $this) {
            if (!$this->inSameClassificationTree($atom->concept)) {
                throw new Exception("Concept of atom '{$atom}' not in same classifcation tree with {$this}", 500);
            } else {
                $atom = new Atom($atom->getId(), $this);
            }
        }

        // Check if atom exists in concept population
        if (in_array($atom->getId(), $this->atomCache, true)) { // strict mode to prevent 'Nesting level too deep' error
            return true;
        } elseif ($this->primaryPlug->atomExists($atom)) {
            $this->atomCache[] = $atom->getId(); // Add to cache
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Return content of all atoms for this concept
     * TODO: refactor when resources (e.g. for update field in UI) can be requested with interface definition
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getAllAtomObjects(): array
    {
        return $this->primaryPlug->getAllAtoms($this);
    }

    /**
     * Returns view data for given atom
     * @param \Ampersand\Core\Atom $atom
     * @return array
     */
    public function getViewData(Atom $atom): array
    {
        if (is_null($this->defaultView)) {
            return [];
        } else {
            return $this->defaultView->getViewData($atom);
        }
    }

    /**
     * Instantiate a new atom
     *
     * @param string $atomId
     * @return \Ampersand\Core\Atom
     */
    public function makeAtom(string $atomId): Atom
    {
        return new Atom($atomId, $this);
    }
    
    /**
     * Creating and adding a new atom to the plug
     * ór adding an existing atom to another concept set (making it a specialization)
     *
     * @param \Ampersand\Core\Atom $atom
     * @return \Ampersand\Core\Atom
     */
    public function addAtom(Atom $atom): Atom
    {
        // Adding atom[A] to [A] ($this)
        if ($atom->concept === $this) {
            if ($atom->exists()) {
                $this->logger->debug("Atom {$atom} already exists in concept");
            } else {
                $this->logger->debug("Add atom {$atom} to plug");
                $this->app->getCurrentTransaction()->addAffectedConcept($this); // Add concept to affected concepts. Needed for conjunct evaluation and transaction management
                
                foreach ($this->getPlugs() as $plug) {
                    $plug->addAtom($atom); // Add to plug
                }
                $this->atomCache[] = $atom->getId(); // Add to cache

                $this->app->eventDispatcher()->dispatch(new AtomEvent($atom), AtomEvent::ADDED);
                $this->logger->info("Atom added to concept: {$atom}");
            }
            return $atom;
        // Adding atom[A] to another concept [B] ($this)
        } else {
            // Check if concept A and concept B are in the same classification tree
            if (!$this->inSameClassificationTree($atom->concept)) {
                throw new Exception("Cannot add {$atom} to concept {$this}, because concepts are not in the same classification tree", 500);
            }
            
            // Check if atom[A] exists. Otherwise it may not be added to concept B
            if (!$atom->exists()) {
                throw new Exception("Cannot add {$atom} to concept {$this}, because atom does not exist", 500);
            }
            
            $atom->concept = $this; // Change concept definition
            return $this->addAtom($atom);
        }
    }
    
    /**
     * Remove an existing atom from a concept set (i.e. removing specialization)
     *
     * @param \Ampersand\Core\Atom $atom
     * @return void
     */
    public function removeAtom(Atom $atom)
    {
        if ($atom->concept != $this) {
            throw new Exception("Cannot remove {$atom} from concept {$this}, because concepts don't match", 500);
        }
        
        // Check if concept is a specialization of another concept
        if (empty($this->directGens)) {
            throw new Exception("Cannot remove {$atom} from concept {$this}, because no generalizations exist", 500);
        }
        if (count($this->directGens) > 1) {
            throw new Exception("Cannot remove {$atom} from concept {$this}, because multiple generalizations exist", 500);
        }
        
        // Check if atom exists
        if ($atom->exists()) {
            $this->logger->debug("Remove atom {$atom} from {$this} in plug");
            $this->app->getCurrentTransaction()->addAffectedConcept($this); // Add concept to affected concepts. Needed for conjunct evaluation and transaction management
            
            foreach ($this->getPlugs() as $plug) {
                $plug->removeAtom($atom); // Remove from concept in plug
            }
            if (($key = array_search($atom->getId(), $this->atomCache)) !== false) {
                unset($this->atomCache[$key]); // Delete from cache
            }

            $this->logger->info("Atom removed from concept: {$atom}");
            
            // Delete all links where $atom is used as src or tgt atom
            // from relations where $this concept (or any of its specializations) is used as src or tgt concept
            $this->deleteAllSpecializationLinks($atom);
        } else {
            $this->logger->debug("Cannot remove atom {$atom} from {$this}, because atom does not exist");
        }
    }
    
    /**
     * Completely delete and atom and all connected links
     *
     * @param \Ampersand\Core\Atom $atom
     * @return void
     */
    public function deleteAtom(Atom $atom)
    {
        if ($atom->exists()) {
            $this->logger->debug("Delete atom {$atom} from plug");
            $this->app->getCurrentTransaction()->addAffectedConcept($this); // Add concept to affected concepts. Needed for conjunct evaluation and transaction management
            
            foreach ($this->getPlugs() as $plug) {
                $plug->deleteAtom($atom); // Delete from plug
            }
            if (($key = array_search($atom->getId(), $this->atomCache)) !== false) {
                unset($this->atomCache[$key]); // Delete from cache
            }

            $this->app->eventDispatcher()->dispatch(new AtomEvent($atom), AtomEvent::DELETED);
            $this->logger->info("Atom deleted: {$atom}");
            
            // Delete all links where $atom is used as src or tgt atom
            $this->deleteAllLinksWithAtom($atom);
        } else {
            $this->logger->debug("Cannot delete atom {$atom}, because it does not exist");
        }
    }

    /**
     * Function to merge two atoms
     * All link from/to the $rightAtom are merged into the $leftAtom
     * The $rightAtom itself is deleted afterwards
     *
     * @param \Ampersand\Core\Atom $leftAtom
     * @param \Ampersand\Core\Atom $rightAtom
     * @return void
     */
    public function mergeAtoms(Atom $leftAtom, Atom $rightAtom)
    {
        $this->logger->info("Request to merge '{$rightAtom}' into '{$leftAtom}'");

        if ($leftAtom->concept != $this) {
            throw new Exception("Cannot merge atom '{$leftAtom}', because it does not match concept '{$this}'", 500);
        }
        
        // Check that left and right atoms are in the same typology.
        if (!$leftAtom->concept->inSameClassificationTree($rightAtom->concept)) {
            throw new Exception("Cannot merge '{$rightAtom}' into '{$leftAtom}', because they not in the same classification tree", 500);
        }

        // Skip when left and right atoms are the same
        if ($leftAtom->getId() === $rightAtom->getId()) {
            $this->logger->warning("Merge not needed, because leftAtom and rightAtom are already the same '{$leftAtom}'");
            return;
        }

        // Check if left and right atoms exist
        if (!$leftAtom->exists()) {
            throw new Exception("Cannot merge '{$rightAtom}' into '{$leftAtom}', because '{$leftAtom}' does not exist", 500);
        }
        if (!$rightAtom->exists()) {
            throw new Exception("Cannot merge '{$rightAtom}' into '{$leftAtom}', because '{$rightAtom}' does not exist", 500);
        }

        // Merge step 0: start with most specific versions of the atoms
        $leftAtom = $leftAtom->getSmallest();
        $rightAtom = $rightAtom->getSmallest();

        // Merge step 1: if right atom is more specific, make left atom also more specific
        if ($leftAtom->concept->hasSpecialization($rightAtom->concept)) {
            $leftAtom = $rightAtom->concept->addAtom($leftAtom);
        }

        // Merge step 2: rename right atom by left atom in relation sets
        foreach ($this->app->getModel()->getRelations() as $relation) {
            // Source
            if ($this->inSameClassificationTree($relation->srcConcept)) {
                // Delete and add links where atom is the source
                foreach ($relation->getAllLinks($rightAtom, null) as $link) {
                    $relation->deleteLink($link); // Delete old link
                    $relation->addLink(new Link($relation, $leftAtom, $link->tgt())); // Add new link
                }
            }
            
            // Target
            if ($this->inSameClassificationTree($relation->tgtConcept)) {
                // Delete and add links where atom is the source
                foreach ($relation->getAllLinks(null, $rightAtom) as $link) {
                    $relation->deleteLink($link); // Delete old link
                    $relation->addLink(new Link($relation, $link->src(), $leftAtom)); // Add new link
                }
            }
        }

        // Merge step 3: delete rightAtom
        $this->deleteAtom($rightAtom);
    }

    /**
     * Delete all links where $atom is used
     *
     * @param \Ampersand\Core\Atom $atom
     * @return void
     */
    protected function deleteAllLinksWithAtom(Atom $atom): void
    {
        foreach ($this->app->getModel()->getRelations() as $relation) {
            /** @var \Ampersand\Core\Relation $relation */
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
    protected function deleteAllSpecializationLinks(Atom $atom): void
    {
        foreach ($this->app->getModel()->getRelations() as $relation) {
            /** @var \Ampersand\Core\Relation $relation */
            if ($atom->concept->hasSpecialization($relation->srcConcept, true)) {
                $relation->deleteAllLinks($atom, 'src');
            }
            if ($atom->concept->hasSpecialization($relation->tgtConcept, true)) {
                $relation->deleteAllLinks($atom, 'tgt');
            }
        }
    }
}
