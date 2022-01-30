<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Ampersand\Log\Logger;
use Ampersand\Core\Relation;
use Ampersand\Core\Concept;
use Ampersand\Interfacing\Ifc;
use Ampersand\Plugs\IfcPlugInterface;
use Ampersand\Rule\Rule;
use Ampersand\Plugs\ViewPlugInterface;
use Ampersand\Role;
use Psr\Cache\CacheItemPoolInterface;
use Ampersand\Plugs\MysqlDB\MysqlDB;
use Ampersand\AmpersandApp;
use Ampersand\Rule\Conjunct;
use Ampersand\Interfacing\View;
use Ampersand\Core\Population;
use Ampersand\Core\Link;
use Ampersand\Core\Atom;
use Ampersand\Exception\AmpersandException;
use Ampersand\Exception\NotDefined\ConceptNotDefined;
use Ampersand\Exception\NotDefined\ConjunctNotDefinedException;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\NotDefined\RelationNotDefined;
use Ampersand\Exception\NotDefined\InterfaceNotDefined;
use Ampersand\Exception\InvalidConfigurationException;
use Ampersand\Exception\NotDefined\RoleNotDefinedException;
use Ampersand\Exception\NotDefined\RuleNotDefinedException;
use Ampersand\Exception\NotDefined\ViewNotDefinedException;
use Ampersand\Misc\ProtoContext;
use Ampersand\Rule\RuleType;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Model
{
    const HASH_ALGORITHM = 'md5';

    /**
     * Logger
     */
    private LoggerInterface $logger;

    /**
     * Specifies which part(s) of the Model are initialized (i.e. when definitions are loaded from the json files)
     *
     * @var string[]
     */
    protected array $initialized = [];

    /**
     * Directory where Ampersand model is generated in
     */
    protected string $folder;

    /**
     * Ampersand compiler version
     */
    public string $compilerVersion;

    /**
     * Model checksum
     */
    public string $checksum;

    /**
     * List of files that contain the generated Ampersand model
     */
    protected array $modelFiles = [];

    /**
     * List of all defined concepts in this Ampersand model
     *
     * @var \Ampersand\Core\Concept[]
     */
    protected array $concepts = [];

    /**
     * List of all defined conjuncts in this Ampersand model
     *
     * @var \Ampersand\Rule\Conjunct[]
     */
    protected array $conjuncts = [];

    /**
     * List of all defined relations in this Ampersand model
     *
     * @var \Ampersand\Core\Relation[]
     */
    protected array $relations = [];

    /**
     * List of all defined interfaces in this Ampersand model
     *
     * @var \Ampersand\Interfacing\Ifc[]
     */
    protected array $interfaces = [];

    /**
     * List of all defined views in this Ampersand model
     *
     * @var \Ampersand\Interfacing\View[]
     */
    protected array $views = [];

    /**
     * List of all defined rules in this Ampersand model
     *
     * @var \Ampersand\Rule\Rule[]
     */
    protected array $rules = [];

    /**
     * List of all defined roles in this Ampersand model
     *
     * @var \Ampersand\Role[]
     */
    protected array $roles = [];

    /**
     * Constructor
     *
     * @param string $folder directory where Ampersand model is generated in
     */
    public function __construct(string $folder, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $fileSystem = new Filesystem;

        if (($this->folder = realpath($folder)) === false) {
            throw new InvalidConfigurationException("Specified folder for Ampersand model does not exist: '{$folder}'");
        }
        
        // Ampersand model files
        $this->modelFiles = [
            'concepts' => $this->folder . '/concepts.json',
            'conjuncts' => $this->folder . '/conjuncts.json',
            'interfaces' => $this->folder . '/interfaces.json',
            'populations' => $this->folder . '/populations.json',
            'relations' => $this->folder . '/relations.json',
            'roles' => $this->folder . '/roles.json',
            'rules' => $this->folder . '/rules.json',
            'settings' => $this->folder . '/settings.json',
            'views' => $this->folder . '/views.json',
        ];

        if (!$fileSystem->exists($this->modelFiles)) {
            throw new InvalidConfigurationException("Not all Ampersand model files are provided. Check model folder '{$this->folder}'");
        }
    }

    /**********************************************************************************************
     * INITIALIZATION
    **********************************************************************************************/

    public function init(AmpersandApp $app): Model
    {
        $this->loadConjuncts(Logger::getLogger('RULEENGINE'), $app, $app->getDefaultStorage(), $app->getConjunctCache());
        $this->loadViews($app->getDefaultStorage());
        $this->loadConcepts(Logger::getLogger('CORE'), $app);
        $this->loadRelations(Logger::getLogger('CORE'), $app);
        $this->loadInterfaces($app->getDefaultStorage());
        $this->loadRules($app->getDefaultStorage(), $app, Logger::getLogger('RULEENGINE'));
        $this->loadRoles();

        $this->checksum = $this->getSetting('compiler.modelHash');
        $this->compilerVersion = $this->getSetting('compiler.version');

        return $this;
    }

    /**
     * Import all concept definitions from json file and instantiate Concept objects
     */
    protected function loadConcepts(LoggerInterface $logger, AmpersandApp $app): void
    {
        $allConceptDefs = (array)json_decode(file_get_contents($this->modelFiles['concepts']), true);
    
        foreach ($allConceptDefs as $conceptDef) {
            $this->concepts[$conceptDef['id']] = new Concept($conceptDef, $logger, $app);
        }

        $this->initialized[] = 'concepts';
    }

    /**
     * Import all role definitions from json file and instantiate Conjunct objects
     */
    protected function loadConjuncts(LoggerInterface $logger, AmpersandApp $app, MysqlDB $database, CacheItemPoolInterface $cachePool): void
    {
        $allConjDefs = (array)json_decode(file_get_contents($this->modelFiles['conjuncts']), true);
    
        foreach ($allConjDefs as $conjDef) {
            $this->conjuncts[$conjDef['id']] = new Conjunct($conjDef, $app, $logger, $database, $cachePool);
        }

        $this->initialized[] = 'conjuncts';
    }

    protected function loadRelations(LoggerInterface $logger, AmpersandApp $app): void
    {
        // Import json file
        $allRelationDefs = (array)json_decode(file_get_contents($this->modelFiles['relations']), true);
    
        $this->relations = [];
        foreach ($allRelationDefs as $relationDef) {
            $relation = new Relation($relationDef, $logger, $app);
            $this->relations[$relation->signature] = $relation;
        }

        $this->initialized[] = 'relations';
    }

    /**
     * Import all interface object definitions from json file and instantiate interfaces
     */
    protected function loadInterfaces(IfcPlugInterface $defaultPlug): void
    {
        $allInterfaceDefs = (array)json_decode(file_get_contents($this->modelFiles['interfaces']), true);
        
        $this->interfaces = [];
        foreach ($allInterfaceDefs as $ifcDef) {
            $ifc = new Ifc($ifcDef['id'], $ifcDef['label'], $ifcDef['isAPI'], $ifcDef['ifcObject'], $defaultPlug, $this);
            $this->interfaces[$ifc->getId()] = $ifc;
        }

        $this->initialized[] = 'interfaces';
    }

    /**
     * Import all view definitions from json file and instantiate View objects
     */
    protected function loadViews(ViewPlugInterface $defaultPlug): void
    {
        $allViewDefs = (array)json_decode(file_get_contents($this->modelFiles['views']), true);
        
        foreach ($allViewDefs as $viewDef) {
            $this->views[$viewDef['label']] = new View($viewDef, $defaultPlug);
        }

        $this->initialized[] = 'views';
    }

    /**
     * Import all rule definitions from json file and instantiate Rule objects
     */
    protected function loadRules(ViewPlugInterface $defaultPlug, AmpersandApp $app, LoggerInterface $logger): void
    {
        $this->rules = [];

        $allRuleDefs = (array) json_decode(file_get_contents($this->modelFiles['rules']), true);
        
        // Signal rules
        foreach ($allRuleDefs['signals'] as $ruleDef) {
            $rule = new Rule($ruleDef, $defaultPlug, RuleType::SIG, $app, $logger);
            $this->rules[$rule->getId()] = $rule;
        }
        
        // Invariant rules
        foreach ($allRuleDefs['invariants'] as $ruleDef) {
            $rule = new Rule($ruleDef, $defaultPlug, RuleType::INV, $app, $logger);
            $this->rules[$rule->getId()] = $rule;
        }

        $this->initialized[] = 'rules';
    }

    /**
     * Import all role definitions from json file and instantiate Role objects
     */
    protected function loadRoles(): void
    {
        $allRoleDefs = (array) json_decode(file_get_contents($this->modelFiles['roles']), true);
        
        foreach ($allRoleDefs as $roleDef) {
            $this->roles[$roleDef['name']] = new Role($roleDef, $this);
        }

        $this->initialized[] = 'roles';
    }

    public function getInitialPopulation(): Population
    {
        $population = new Population($this, $this->logger);
        $population->loadFromPopulationFile($this->getFileContent('populations'));
        return $population;
    }

    /**
     * Return meta population for this Ampersand model
     *
     * Population is added to user population by SystemContext grinder in Ampersand compiler
     */
    public function getMetaPopulation(): Population
    {
        // Start with initial population
        $population = $this->getInitialPopulation();

        // Filter by concepts defined in ProtoContext
        $population = $population->filterAtoms(function (Atom $atom) {
            return ProtoContext::containsConcept($atom->concept);
        });
        
        // Filter by relations defined in ProtoContext
        $population = $population->filterLinks(function (Link $link) {
            return ProtoContext::containsRelation($link->relation());
        });

        return $population;
    }

    /**********************************************************************************************
     * CONCEPTS
    **********************************************************************************************/
    /**
     * Returns list with all concept objects
     *
     * @return \Ampersand\Core\Concept[]
     */
    public function getAllConcepts(): array
    {
        if (!in_array('concepts', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }
        
        return $this->concepts;
    }

    /**
     * Return concept object given a concept identifier
     */
    public function getConcept(string $conceptId): Concept
    {
        if (!array_key_exists($conceptId, $concepts = $this->getAllConcepts())) {
            throw new ConceptNotDefined("Concept '{$conceptId}' is not defined");
        }
         
        return $concepts[$conceptId];
    }
    
    /**
     * Return concept object given a concept label
     *
     * @param string $conceptLabel unescaped concept name
     */
    public function getConceptByLabel(string $conceptLabel): Concept
    {
        foreach ($this->getAllConcepts() as $concept) {
            if ($concept->label === $conceptLabel) {
                return $concept;
            }
        }
        
        throw new ConceptNotDefined("Concept '{$conceptLabel}' is not defined");
    }
    
    public function getSessionConcept(): Concept
    {
        return $this->getConcept('SESSION');
    }

    public function getRoleConcept(): Concept
    {
        return $this->getConceptByLabel(ProtoContext::CPT_ROLE);
    }

    public function getInterfaceConcept(): Concept
    {
        return $this->getConceptByLabel(ProtoContext::CPT_IFC);
    }

    /**********************************************************************************************
     * RELATIONS
    **********************************************************************************************/

    /**
     * Returns list of all relation definitions
     *
     * @throws \Ampersand\Exception\FatalException when relations are not loaded (yet) because model is not initialized
     * @return \Ampersand\Core\Relation[]
     */
    public function getRelations(): array
    {
        if (!in_array('relations', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }
         
        return $this->relations;
    }

    /**
     * Return relation object
     *
     * @throws \Ampersand\Exception\NotDefined\RelationNotDefined if relation is not defined
     */
    public function getRelation(string $relationSignature, ?Concept $srcConcept = null, ?Concept $tgtConcept = null): Relation
    {
        if (!in_array('relations', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }
        
        // If relation can be found by its fullRelationSignature return the relation
        if (array_key_exists($relationSignature, $this->relations)) {
            $relation = $this->relations[$relationSignature];
            
            // If srcConceptName and tgtConceptName are provided, check that they match the found relation
            if (!is_null($srcConcept) && !in_array($srcConcept, $relation->srcConcept->getSpecializationsIncl())) {
                throw new AmpersandException("Provided src concept '{$srcConcept}' does not match the relation '{$relation}'");
            }
            if (!is_null($tgtConcept) && !in_array($tgtConcept, $relation->tgtConcept->getSpecializationsIncl())) {
                throw new AmpersandException("Provided tgt concept '{$tgtConcept}' does not match the relation '{$relation}'");
            }
            
            return $relation;
        }
        
        // Else try to find the relation by its name, srcConcept and tgtConcept
        if (!is_null($srcConcept) && !is_null($tgtConcept)) {
            foreach ($this->relations as $relation) {
                if ($relation->name == $relationSignature
                        && in_array($srcConcept, $relation->srcConcept->getSpecializationsIncl())
                        && in_array($tgtConcept, $relation->tgtConcept->getSpecializationsIncl())
                  ) {
                    return $relation;
                }
            }
        }
        
        // Else
        throw new RelationNotDefined("Relation '{$relationSignature}[{$srcConcept}*{$tgtConcept}]' is not defined");
    }

    /**********************************************************************************************
     * INTERFACES
    **********************************************************************************************/
    /**
     * Returns all interfaces
     *
     * @return \Ampersand\Interfacing\Ifc[]
     */
    public function getAllInterfaces(): array
    {
        if (!in_array('interfaces', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }
        
        return $this->interfaces;
    }

    /**
     * Returns if interface exists
     */
    public function interfaceExists(string $ifcId): bool
    {
        return array_key_exists($ifcId, $this->getAllInterfaces());
    }

    /**
     * Returns toplevel interface object
     *
     * If fallbackOnLabel is set to true, the param $ifcId may also contain an interface label (i.e. name as defined in &-script)
     * @throws \Ampersand\Exception\NotDefined\InterfaceNotDefined when interface does not exist
     */
    public function getInterface(string $ifcId, bool $fallbackOnLabel = false): Ifc
    {
        if (!array_key_exists($ifcId, $interfaces = $this->getAllInterfaces())) {
            if ($fallbackOnLabel) {
                return $this->getInterfaceByLabel($ifcId);
            } else {
                throw new InterfaceNotDefined("Interface '{$ifcId}' is not defined");
            }
        }

        return $interfaces[$ifcId];
    }

    /**
     * Undocumented function
     *
     * @throws \Ampersand\Exception\NotDefined\InterfaceNotDefined when interface does not exist
     */
    public function getInterfaceByLabel(string $ifcLabel): Ifc
    {
        foreach ($this->getAllInterfaces() as $interface) {
            if ($interface->getLabel() === $ifcLabel) {
                return $interface;
            }
        }
        
        throw new InterfaceNotDefined("Interface with label '{$ifcLabel}' is not defined");
    }

    /**********************************************************************************************
     * VIEWS
    **********************************************************************************************/
    /**
     * Returns array with all view objects
     *
     * @return \Ampersand\Interfacing\View[]
     */
    public function getAllViews(): array
    {
        if (!in_array('views', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }
         
        return $this->views;
    }

    /**
     * Return view object
     *
     */
    public function getView(string $viewLabel): View
    {
        if (!in_array('views', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }

        if (!array_key_exists($viewLabel, $this->views)) {
            throw new ViewNotDefinedException("View '{$viewLabel}' is not defined");
        }
    
        return $this->views[$viewLabel];
    }

    /**********************************************************************************************
     * RULES
    **********************************************************************************************/
    /**
     * Get list with all rules
     *
     * @return Rule[]
     */
    public function getAllRules(string $type = null): array
    {
        if (!in_array('rules', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }

        switch ($type) {
            case null: // all rules
                return $this->rules;
                break;
            case 'signal': // all signal rules
                return array_values(array_filter($this->rules, function (Rule $rule) {
                    return $rule->isSignalRule();
                }));
                break;
            case 'invariant': // all invariant rules
                return array_values(array_filter($this->rules, function (Rule $rule) {
                    return $rule->isInvariantRule();
                }));
                break;
            default:
                throw new FatalException("Specified rule type '{$type}' is wrong");
                break;
        }
    }

    /**
     * Get rule with a given rule name
     *
     */
    public function getRule(string $ruleName): Rule
    {
        if (!in_array('rules', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }

        if (!array_key_exists($ruleName, $this->rules)) {
            throw new RuleNotDefinedException("Rule '{$ruleName}' is not defined");
        }

        return $this->rules[$ruleName];
    }

    /**********************************************************************************************
     * CONJUNCTS
    **********************************************************************************************/
    /**
     * Returns array with all conjunct objects
     *
     * @return \Ampersand\Rule\Conjunct[]
     */
    public function getAllConjuncts(): array
    {
        if (!in_array('conjuncts', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }
         
        return $this->conjuncts;
    }

    /**
     * Return conjunct object
     *
     */
    public function getConjunct(string $conjId): Conjunct
    {
        if (!in_array('conjuncts', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }

        if (!array_key_exists($conjId, $this->conjuncts)) {
            throw new ConjunctNotDefinedException("Conjunct '{$conjId}' is not defined");
        }
    
        return $this->conjuncts[$conjId];
    }

    /**********************************************************************************************
     * ROLES
    **********************************************************************************************/
    /**
     * Returns array with all role objects
     *
     * @return \Ampersand\Role[]
     */
    public function getAllRoles(): array
    {
        if (!in_array('roles', $this->initialized)) {
            throw new FatalException("Ampersand model is not yet initialized");
        }
         
        return $this->roles;
    }

    /**
     * Return role object
     *
     */
    public function getRoleById(string $roleId): Role
    {
        foreach ($this->getAllRoles() as $role) {
            if ($role->getId() === $roleId) {
                return $role;
            }
        }
        
        throw new RoleNotDefinedException("Role with id '{$roleId}' is not defined");
    }
    
    /**
     * Return role object
     *
     */
    public function getRoleByName(string $roleName): Role
    {
        if (!array_key_exists($roleName, $roles = $this->getAllRoles())) {
            throw new RoleNotDefinedException("Role '{$roleName}' is not defined");
        }
    
        return $roles[$roleName];
    }
    
    /**********************************************************************************************
     * MISC
    **********************************************************************************************/
    public function getFolder(): string
    {
        return $this->folder;
    }

    public function getFilePath(string $filename): string
    {
        if (!array_key_exists($filename, $this->modelFiles)) {
            throw new FatalException("File '{$filename}' is not part of the specified Ampersand model files");
        }

        return $this->modelFiles[$filename];
    }

    protected function loadFile(string $filename): mixed
    {
        $decoder = new JsonDecode(false);
        return $decoder->decode(file_get_contents($this->getFilePath($filename)), JsonEncoder::FORMAT);
    }

    protected function getFileContent(string $filename): mixed
    {
        static $loadedFiles = [];

        if (!array_key_exists($filename, $loadedFiles)) {
            $loadedFiles[$filename] = $this->loadFile($filename);
        }

        return $loadedFiles[$filename];
    }

    protected function getSetting(string $setting): mixed
    {
        $settings = $this->getFileContent('settings');
        
        if (!property_exists($settings, $setting)) {
            throw new FatalException("Undefined setting '{$setting}'");
        }

        return $settings->$setting;
    }
}
