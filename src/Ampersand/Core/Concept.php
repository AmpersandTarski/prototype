<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Core;

use Ampersand\Plugs\MysqlDB\MysqlDBTable;
use Ampersand\Plugs\MysqlDB\MysqlDBTableCol;
use Ampersand\Core\Atom;
use Ampersand\Core\TType;
use Ampersand\Plugs\ConceptPlugInterface;
use Psr\Log\LoggerInterface;
use Ampersand\AmpersandApp;
use Ampersand\Event\AtomEvent;
use Ampersand\Exception\AtomNotFoundException;
use Ampersand\Exception\NotDefined\NotDefinedException;
use Ampersand\Exception\TypeCheckerException;
use Ramsey\Uuid\Uuid;
use Ampersand\Interfacing\View;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Concept
{
    /**
     * Logger
     */
    private LoggerInterface $logger;

    /**
     * Reference to Ampersand app for which this concept is defined
     */
    protected AmpersandApp $app;
    
    /**
     * Dependency injection of ConceptPlug implementation
     *
     * There must at least be one plug for every concept
     *
     * @var \Ampersand\Plugs\ConceptPlugInterface[]
     */
    protected array $plugs = [];
    
    /**
     * Primairy implementation of ConceptPlug
     *
     * This is e.g. where atom existance check is done
     */
    protected ConceptPlugInterface $primaryPlug;
    
    /**
     * Definition from which Concept object is created
     */
    private array $def;
    
    /**
     * Name (and unique escaped identifier) of concept as defined in Ampersand script
     *
     * TODO: rename var to $id
     */
    public string $name;
    
    /**
     * Unescaped name of concept as defined in Ampersand script
     */
    public string $label;
    
    /**
     * Specifies technical representation of atoms of this concept
     */
    public TType $type;

    /**
     * Specifies if new atom identifiers must be prefixed with the concept name, e.g. 'ConceptA_<uuid>'
     */
    protected bool $prefixAtomIdWithConceptName;
    
    /**
     * List of conjuncts that are affected by creating or deleting an atom of this concept
     *
     * @var \Ampersand\Rule\Conjunct[]
     */
    protected array $relatedConjuncts = [];
    
    /**
     * List of concepts (name) that are specializations of this concept
     *
     * @var string[]
     */
    private array $specializations = [];
    
    /**
     * List of concepts (name) that are direct specializations of this concept
     *
     * @var string[]
     */
    private array $directSpecs = [];
    
    /**
     * List of concepts (name) that are generalizations of this concept
     *
     * @var string[]
     */
    private array $generalizations = [];
    
    /**
     * List of concepts (name) that are direct generalizations of this concept
     *
     * @var string[]
     */
    private array $directGens = [];
    
    /**
     * Concept identifier of largest generalization for this concept
     */
    private string $largestConceptId;
    
    /**
     * List of interface identifiers that have this concept as src concept
     *
     * @var string[]
     */
    protected array $interfaceIds = [];
    
    /**
     * Default view object for atoms of this concept
     */
    private ?View $defaultView = null;

    /**
     * Contains information about mysql table and columns in which this concept is administrated
     */
    private MysqlDBTable $mysqlConceptTable;
    
    /**
     * List with atom identifiers that exist in the concept
     *
     * Used to prevent unnecessary checks if atom exists in plug
     *
     * @var string[]
     */
    private array $atomCache = [];
    
    /**
     * Constructor
     */
    public function __construct(array $conceptDef, LoggerInterface $logger, AmpersandApp $app)
    {
        $this->logger = $logger;
        $this->app = $app;
        
        $this->def = $conceptDef;
        
        $this->name = $conceptDef['id'];
        $this->label = $conceptDef['label'];
        $this->type = TType::from($conceptDef['type']);

        $this->prefixAtomIdWithConceptName = $app->getSettings()->get('core.concept.prefixAtomIdWithConceptName');
        
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
            throw new NotDefinedException("Concept table information not defined for concept {$this->label}");
        }
    }

    /**
     * Temporary function to manually add a more optimize query for getting all atoms at once
     * Default view and query belong together
     * TODO: replace hack by proper implementation in Ampersand generator
     */
    public function setAllAtomsQuery(string $viewId, string $query): void
    {
        $this->defaultView = $this->app->getModel()->getView($viewId);
        $this->mysqlConceptTable->allAtomsQuery = $query;
    }
    
    /**
     * Function is called when object is treated as a string
     */
    public function __toString(): string
    {
        return $this->label;
    }

    /**
     * Get escaped name of concept
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
     * Specifies if concept is object
     */
    public function isObject(): bool
    {
        return $this->type === TType::OBJECT;
    }
    
    /**
     * Check if concept is file object
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

    public function isONE(): bool
    {
        return $this->label === 'ONE';
    }
    
    /**
     * Check if this concept is a generalization of another given concept
     *
     * Use thisIncluded to specify that this concept itself is included in the comparison
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
     * Use thisIncluded to specify that this concept itself is included in the comparison
     */
    public function hasGeneralization(Concept $concept, bool $thisIncluded = false): bool
    {
        if ($thisIncluded && $concept == $this) {
            return true;
        }
        
        return in_array($concept->name, $this->generalizations);
    }
    
    /**
     * Checks if this concept is in same classification branch as the provided concept
     */
    public function inSameClassificationBranch(Concept $concept): bool
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

    public function isRoot(): bool
    {
        return empty($this->generalizations);
    }
    
    /**
     * Array of concepts of which this concept is a generalization.
     *
     * @return \Ampersand\Core\Concept[]
     */
    public function getSpecializations(bool $onlyDirectSpecializations = false): array
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
     * @return \Ampersand\Core\Concept[]
     */
    public function getGeneralizations(bool $onlyDirectGeneralizations = false): array
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
    public function getSpecializationsIncl(): array
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
    public function getGeneralizationsIncl(): array
    {
        $generalizations = $this->getGeneralizations();
        $generalizations[] = $this;
        return $generalizations;
    }
    
    /**
     * Returns largest generalization concept (can be itself)
     */
    public function getLargestConcept(): Concept
    {
        return $this->app->getModel()->getConcept($this->largestConceptId);
    }
    
    /**
     * Returns array with signal conjuncts that are affected by creating or deleting an atom of this concept
     *
     * @return \Ampersand\Rule\Conjunct[]
     */
    public function getRelatedConjuncts(): array
    {
        return $this->relatedConjuncts;
    }

    /**
     * List relations where this concept is the src or tgt
     * @return \Ampersand\Core\Relation[]
     */
    public function getRelatedRelations(): array
    {
        return array_filter($this->app->getModel()->getRelations(), function (Relation $relation) {
            $concepts = $this->getGeneralizationsIncl();
            return in_array($relation->srcConcept, $concepts)
                || in_array($relation->tgtConcept, $concepts);
        });
    }
    
    /**
     * Returns database table info for concept
     */
    public function getConceptTableInfo(): MysqlDBTable
    {
        return $this->mysqlConceptTable;
    }

    /**
     * Get registered plugs for this concept
     *
     * @return \Ampersand\Plugs\ConceptPlugInterface[]
     */
    public function getPlugs(): array
    {
        if (empty($this->plugs)) {
            throw new NotDefinedException("No plug(s) provided for concept {$this}");
        }
        return $this->plugs;
    }

    /**
     * Add plug for this concept
     */
    public function addPlug(ConceptPlugInterface $plug): void
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
     */
    public function clearAtomCache(): void
    {
        $this->atomCache = [];
    }
    
    /**
     * Generate a new atom identifier for this concept
     */
    public function createNewAtomId(bool $prefixAtomIdWithConceptName = null): string
    {
        $prefixAtomIdWithConceptName ??= $this->prefixAtomIdWithConceptName;

        // TODO: remove this hack with _AI (autoincrement feature)
        if (strpos($this->name, '_AI') !== false && $this->type === TType::INTEGER) {
            $firstCol = current($this->mysqlConceptTable->getCols());
            $query = "SELECT MAX(\"{$firstCol->getName()}\") as \"MAX\" FROM \"{$this->mysqlConceptTable->getName()}\"";
             
            $result = array_column((array)$this->primaryPlug->executeCustomSQLQuery($query), 'MAX');
    
            if (empty($result)) {
                return (string) 1;
            } else {
                return (string) ($result[0] + 1);
            }
        }

        /**
         * Source: https://uuid.ramsey.dev/en/latest/rfc4122/version4.html
         * Version 4 UUIDs are perhaps the most popular form of UUID. They are randomly-generated and do
         * not contain any information about the time they are created or the machine that generated them.
         * If you don’t care about this information, then a version 4 UUID might be perfect for your needs.
         */
        return $prefixAtomIdWithConceptName ? $this->name . '_' . Uuid::uuid4()->toString() : Uuid::uuid4()->toString();
    }
    
    /**
     * Instantiate new Atom object in backend
     * NB! this does not result automatically in a database insert
     */
    public function createNewAtom(): Atom
    {
        return new Atom($this->createNewAtomId(), $this);
    }
    
    /**
     * Check if atom exists
     */
    public function atomExists(Atom $atom): bool
    {
        // Convert atom to more generic/specific concept if needed
        if ($atom->concept !== $this) {
            if (!$this->inSameClassificationBranch($atom->concept)) {
                throw new TypeCheckerException("Concept of atom '{$atom}' not in same classifcation branch as {$this}. Try adding 'CLASSIFY {$atom->concept} ISA {$this}' or vice-versa");
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
     */
    public function makeAtom(string $atomId): Atom
    {
        return new Atom($atomId, $this);
    }
    
    /**
     * Creating and adding a new atom to the plug
     * ór adding an existing atom to another concept set (making it a specialization)
     *
     * @param bool $populateDefaults specifies if default src/tgt values for relations must be populated also
     */
    public function addAtom(Atom $atom, bool $populateDefaults = true): Atom
    {
        // Adding atom[A] to [A] ($this)
        if ($atom->concept === $this) {
            if ($atom->exists()) {
                $this->logger->debug("Atom {$atom} already exists in concept");
            } else {
                $this->logger->debug("Add atom {$atom} to plug");
                $transaction = $this->app->getCurrentTransaction();
                $transaction->addAffectedConcept($this); // Add concept to affected concepts. Needed for conjunct evaluation and transaction management
                
                foreach ($this->getPlugs() as $plug) {
                    $plug->addAtom($atom); // Add to plug
                }
                $this->atomCache[] = $atom->getId(); // Add to cache

                $this->app->eventDispatcher()->dispatch(new AtomEvent($atom, $transaction), AtomEvent::ADDED);
                $this->logger->info("Atom added to concept: {$atom}");
            }

            // Add default values in related relations
            if ($populateDefaults) {
                $this->addDefaultsFor($atom);
            }
            return $atom;
        // Adding atom[A] to another concept [B] ($this)
        } else {
            // Check if concept A and concept B are in the same classification tree
            if (!$this->inSameClassificationBranch($atom->concept)) {
                throw new TypeCheckerException("Cannot add {$atom} to concept {$this}, because neither concept includes the other. Try adding 'CLASSIFY {$atom->concept} ISA {$this}' or vice-versa");
            }
            
            // Check if atom[A] exists. Otherwise it may not be added to concept B
            if (!$atom->exists()) {
                throw new AtomNotFoundException("Cannot add {$atom} to concept {$this}, because atom does not exist");
            }
            
            $atom->concept = $this; // Change concept definition
            return $this->addAtom($atom, false);
        }
    }
    
    /**
     * Remove an existing atom from a concept set (i.e. removing specialization)
     */
    public function removeAtom(Atom $atom): void
    {
        if ($atom->concept != $this) {
            throw new TypeCheckerException("Cannot remove {$atom} from concept {$this}, because concepts don't match");
        }
        
        // Check if concept is a specialization of another concept
        if (empty($this->directGens)) {
            throw new TypeCheckerException("Cannot remove {$atom} from concept {$this}, because no generalizations exist");
        }
        if (count($this->directGens) > 1) {
            throw new TypeCheckerException("Cannot remove {$atom} from concept {$this}, because multiple generalizations exist");
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
     */
    public function deleteAtom(Atom $atom): void
    {
        if ($atom->exists()) {
            $this->logger->debug("Delete atom {$atom} from plug");
            $transaction = $this->app->getCurrentTransaction();
            $transaction->addAffectedConcept($this); // Add concept to affected concepts. Needed for conjunct evaluation and transaction management
            
            foreach ($this->getPlugs() as $plug) {
                $plug->deleteAtom($atom); // Delete from plug
            }
            if (($key = array_search($atom->getId(), $this->atomCache)) !== false) {
                unset($this->atomCache[$key]); // Delete from cache
            }

            $this->app->eventDispatcher()->dispatch(new AtomEvent($atom, $transaction), AtomEvent::DELETED);
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
     */
    public function mergeAtoms(Atom $leftAtom, Atom $rightAtom): void
    {
        $this->logger->info("Request to merge '{$rightAtom}' into '{$leftAtom}'");

        if ($leftAtom->concept != $this) {
            throw new TypeCheckerException("Cannot merge atom '{$leftAtom}', because it does not match concept '{$this}'");
        }
        
        // Check that left and right atoms are in the same typology.
        if (!$leftAtom->concept->inSameClassificationBranch($rightAtom->concept)) {
            throw new TypeCheckerException("Cannot merge '{$rightAtom}' into '{$leftAtom}', because neither concept includes the other. Try adding 'CLASSIFY {$rightAtom->concept} ISA {$leftAtom->concept}' or vice-versa");
        }

        // Skip when left and right atoms are the same
        if ($leftAtom->getId() === $rightAtom->getId()) {
            $this->logger->warning("Merge not needed, because leftAtom and rightAtom are already the same '{$leftAtom}'");
            return;
        }

        // Check if left and right atoms exist
        if (!$leftAtom->exists()) {
            throw new AtomNotFoundException("Cannot merge '{$rightAtom}' into '{$leftAtom}', because '{$leftAtom}' does not exist");
        }
        if (!$rightAtom->exists()) {
            throw new AtomNotFoundException("Cannot merge '{$rightAtom}' into '{$leftAtom}', because '{$rightAtom}' does not exist");
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
            if ($this->inSameClassificationBranch($relation->srcConcept)) {
                // Delete and add links where atom is the source
                foreach ($relation->getAllLinks($rightAtom, null) as $link) {
                    $relation->deleteLink($link); // Delete old link
                    $relation->addLink(new Link($relation, $leftAtom, $link->tgt())); // Add new link
                }
            }
            
            // Target
            if ($this->inSameClassificationBranch($relation->tgtConcept)) {
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

    public function regenerateAllAtomIds(bool $prefixWithConceptName = null): void
    {
        foreach ($this->getAllAtomObjects() as $atom) {
            $atom->rename($this->createNewAtomId($prefixWithConceptName));
        }
    }

    /**
     * Delete all links where $atom is used
     */
    protected function deleteAllLinksWithAtom(Atom $atom): void
    {
        foreach ($this->app->getModel()->getRelations() as $relation) {
            if ($atom->concept->inSameClassificationBranch($relation->srcConcept)) {
                $relation->deleteAllLinks($atom, SrcOrTgt::SRC);
            }
            if ($atom->concept->inSameClassificationBranch($relation->tgtConcept)) {
                $relation->deleteAllLinks($atom, SrcOrTgt::TGT);
            }
        }
    }
    
    /**
     * Delete all links where $atom is used as src or tgt atom
     * from relations where $atom's concept (or any of its specializations) is used as src or tgt concept
     */
    protected function deleteAllSpecializationLinks(Atom $atom): void
    {
        foreach ($this->app->getModel()->getRelations() as $relation) {
            if ($atom->concept->hasSpecialization($relation->srcConcept, true)) {
                $relation->deleteAllLinks($atom, SrcOrTgt::SRC);
            }
            if ($atom->concept->hasSpecialization($relation->tgtConcept, true)) {
                $relation->deleteAllLinks($atom, SrcOrTgt::TGT);
            }
        }
    }

    // TODO: Query this every time?? or put defaults in concept class during init???
    protected function addDefaultsFor(Atom $atom): void
    {
        foreach ($this->getRelatedRelations() as $relation) {
            // Add tgt defaults
            if ($relation->hasDefaultTgtValues() && $atom->concept->inSameClassificationBranch($relation->srcConcept)) {
                foreach ($relation->getDefaultTgtValues() as $value) {
                    $atom->link($value, $relation, false)->add();
                }
            }
            // Add src defaults
            if ($relation->hasDefaultSrcValues() && $atom->concept->inSameClassificationBranch($relation->tgtConcept)) {
                foreach ($relation->getDefaultSrcValues() as $value) {
                    $atom->link($value, $relation, true)->add();
                }
            }
        }
    }
}
