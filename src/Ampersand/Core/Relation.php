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
use Ampersand\Plugs\RelationPlugInterface;
use Psr\Log\LoggerInterface;
use Ampersand\AmpersandApp;
use Ampersand\Event\LinkEvent;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Relation
{
    
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
    protected $relatedConjuncts = [];

    /**
     * List of default SRC atom values that is populated for this relation when a new TGT atom is created
     * The value can start with '{php}' to indicate that it is a php function that needs to be evaluated
     * @var string[]
     */
    protected array $defaultSrc = [];

    /**
     * List of default TGT atom values that is populated for this relation when a new SRC atom is created
     * The value can start with '{php}' to indicate that it is a php function that needs to be evaluated
     * @var string[]
     */
    protected array $defaultTgt = [];
    
    /**
     *
     * @var \Ampersand\Plugs\MysqlDB\MysqlDBRelationTable
     */
    private $mysqlTable;
    
    /**
     * Constructor
     */
    public function __construct(array $relationDef, LoggerInterface $logger, AmpersandApp $app)
    {
        $this->logger = $logger;
        $this->app = $app;

        $this->name = $relationDef['name'];
        $this->srcConcept = $app->getModel()->getConcept($relationDef['srcConceptId']);
        $this->tgtConcept = $app->getModel()->getConcept($relationDef['tgtConceptId']);
        
        $this->signature = $relationDef['signature'];
        
        $this->isUni = $relationDef['uni'];
        $this->isTot = $relationDef['tot'];
        $this->isInj = $relationDef['inj'];
        $this->isSur = $relationDef['sur'];
        $this->isProp = $relationDef['prop'];

        $this->defaultSrc = $relationDef['defaultSrc'];
        $this->defaultTgt = $relationDef['defaultTgt'];
        
        foreach ((array)$relationDef['affectedConjuncts'] as $conjId) {
            $conj = $app->getModel()->getConjunct($conjId);
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
     */
    public function __toString(): string
    {
        return $this->getSignature();
    }
    
    /**
     * Return signature of relation (format: relName[srcConceptName*tgtConceptName])
     */
    public function getSignature(): string
    {
        return "{$this->name}[{$this->srcConcept}*{$this->tgtConcept}]";
    }
    
    /**
     * Returns array with signal conjuncts that are affected by updating this Relation
     *
     * @return \Ampersand\Rule\Conjunct[]
     */
    public function getRelatedConjuncts(): array
    {
        return $this->relatedConjuncts;
    }
    
    public function getMysqlTable(): MysqlDBRelationTable
    {
        return $this->mysqlTable;
    }

    /**
     * Get registered plugs for this relation
     *
     * @return \Ampersand\Plugs\RelationPlugInterface[]
     */
    public function getPlugs(): array
    {
        if (empty($this->plugs)) {
            throw new Exception("No plug(s) provided for relation {$this->getSignature()}", 500);
        }
        return $this->plugs;
    }

    /**
     * Add plug for this relation
     */
    public function addPlug(RelationPlugInterface $plug): void
    {
        if (!in_array($plug, $this->plugs)) {
            $this->plugs[] = $plug;
        }
        if (count($this->plugs) === 1) {
            $this->primaryPlug = $plug;
        }
    }

    /**
     * Instantiate new Link object for this relation
     */
    public function makeLink(string $srcId, string $tgtId): Link
    {
        return new Link($this, new Atom($srcId, $this->srcConcept), new Atom($tgtId, $this->tgtConcept));
    }
    
    /**
     * Check if link (tuple of src and tgt atom) exists in this relation
     */
    public function linkExists(Link $link): bool
    {
        $this->logger->debug("Checking if link {$link} exists in plug");
        
        return $this->primaryPlug->linkExists($link);
    }
    
    /**
    * Get all links for this relation
    *
    * If src and/or tgt atom is specified only links are returned with these atoms
    * @return \Ampersand\Core\Link[]
    */
    public function getAllLinks(?Atom $srcAtom = null, ?Atom $tgtAtom = null): array
    {
        return $this->primaryPlug->getAllLinks($this, $srcAtom, $tgtAtom);
    }
    
    /**
     * Add link to this relation
     */
    public function addLink(Link $link): void
    {
        $this->logger->debug("Add link {$link} to plug");
        $transaction = $this->app->getCurrentTransaction();
        $transaction->addAffectedRelations($this); // Add relation to affected relations. Needed for conjunct evaluation and transaction management
        
        // Ensure that atoms exist in their concept tables
        $link->src()->add(false); // TODO: remove when we know for sure that this is guaranteed by calling functions
        $link->tgt()->add(false); // TODO: remove when we know for sure that this is guaranteed by calling functions
        
        foreach ($this->getPlugs() as $plug) {
            $plug->addLink($link);
        }

        $this->app->eventDispatcher()->dispatch(new LinkEvent($link, $transaction), LinkEvent::ADDED);
        $this->logger->info("Link added to relation: {$link}");
    }
    
    /**
     * Delete link from this relation
     */
    public function deleteLink(Link $link): void
    {
        $this->logger->debug("Delete link {$link} from plug");
        $transaction = $this->app->getCurrentTransaction();
        $transaction->addAffectedRelations($this); // Add relation to affected relations. Needed for conjunct evaluation and transaction management
        
        foreach ($this->getPlugs() as $plug) {
            $plug->deleteLink($link);
        }

        $this->app->eventDispatcher()->dispatch(new LinkEvent($link, $transaction), LinkEvent::DELETED);
        $this->logger->info("Link deleted: {$link}");
    }
    
    /**
     * Delete all links where specified atom is used
     *
     * SrcOrTgt specifies to delete all link with $atom as src, tgt or both (null/not provided)
     * TODO: use enum here
     */
    public function deleteAllLinks(Atom $atom, string $srcOrTgt): void
    {
        switch ($srcOrTgt) {
            case 'src':
            case 'tgt':
                $this->logger->debug("Deleting all links in relation '{$this}' with {$srcOrTgt} '{$atom}'");
                break;
            default:
                throw new Exception("Unknown/unsupported param option '{$srcOrTgt}'. Supported options are 'src' or 'tgt'", 500);
                break;
        }

        // Add relation to affected relations. Needed for conjunct evaluation and transaction management
        $this->app->getCurrentTransaction()->addAffectedRelations($this);

        foreach ($this->getPlugs() as $plug) {
            $plug->deleteAllLinks($this, $atom, $srcOrTgt);
        }
        $this->logger->info("Deleted all links in relation '{$this}' where atom '{$atom}' is used as '{$srcOrTgt}'");
    }

    /**
     * Empty relation (i.e. delete all links)
     */
    public function empty(): void
    {
        $this->logger->debug("Deleting all links in relation {$this}");

        // Add relation to affected relations. Needed for conjunct evaluation and transaction management
        $this->app->getCurrentTransaction()->addAffectedRelations($this);

        foreach ($this->getPlugs() as $plug) {
            $plug->emptyRelation($this);
        }
        $this->logger->info("Deleted all links in relation: {$this}");
    }

    public function hasDefaultSrcValues(): bool
    {
        return !empty($this->defaultSrc);
    }

    public function hasDefaultTgtValues(): bool
    {
        return !empty($this->defaultTgt);
    }

    public function getDefaultSrcValues(): array
    {
        return array_map('self::resolveDefaultValue', $this->defaultSrc);
    }

    public function getDefaultTgtValues(): array
    {
        return array_map('self::resolveDefaultValue', $this->defaultTgt);
    }

    protected static function resolveDefaultValue(string $value): string
    {
        if (substr($value, 0, 5) === '{php}') {
            $code = 'return('.substr($value, 5).');';
            $result = eval($code);
            if (!is_scalar($result)) {
                throw new Exception("Evaluation of '{$value}' does not resolve to a scalar", 500);
            }
            return (string) $result;
        }
        return $value;
    }
}
