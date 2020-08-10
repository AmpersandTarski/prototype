<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Exception;
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
use Ampersand\Exception\RelationNotDefined;
use Ampersand\Exception\InterfaceNotDefined;
use Ampersand\Misc\ProtoContext;

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
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Specifies which part(s) of the Model are initialized (i.e. when definitions are loaded from the json files)
     *
     * @var string[]
     */
    protected $initialized = [];

    /**
     * Directory where Ampersand model is generated in
     *
     * @var string
     */
    protected $folder;

    /**
     * Filepath for saving checksums of generated Ampersand model
     *
     * @var string
     */
    protected $checksumFile;

    /**
     * List of files that contain the generated Ampersand model
     *
     * @var array
     */
    protected $modelFiles = [];

    /**
     * List of all defined concepts in this Ampersand model
     *
     * @var \Ampersand\Core\Concept[]
     */
    protected $concepts = [];

    /**
     * List of all defined conjuncts in this Ampersand model
     *
     * @var \Ampersand\Rule\Conjunct[]
     */
    protected $conjuncts = [];

    /**
     * List of all defined relations in this Ampersand model
     *
     * @var \Ampersand\Core\Relation[]
     */
    protected $relations = [];

    /**
     * List of all defined interfaces in this Ampersand model
     *
     * @var \Ampersand\Interfacing\Ifc[]
     */
    protected $interfaces = [];

    /**
     * List of all defined views in this Ampersand model
     *
     * @var \Ampersand\Interfacing\View[]
     */
    protected $views = [];

    /**
     * List of all defined rules in this Ampersand model
     *
     * @var \Ampersand\Rule\Rule[]
     */
    protected $rules = [];

    /**
     * List of all defined roles in this Ampersand model
     *
     * @var \Ampersand\Role[]
     */
    protected $roles = [];

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
            throw new Exception("Specified folder for Ampersand model does not exist: '{$folder}'", 500);
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
            throw new Exception("Not all Ampersand model files are provided. Check model folder '{$this->folder}'", 500);
        }

        $this->checksumFile = "{$this->folder}/checksums.txt";
        
        // Write checksum file if not yet exists
        if (!file_exists($this->checksumFile)) {
            $this->writeChecksumFile();
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

        return $this;
    }

    /**
     * Import all concept definitions from json file and instantiate Concept objects
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Ampersand\AmpersandApp $app
     * @return void
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
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Ampersand\AmpersandApp $app
     * @param \Ampersand\Plugs\MysqlDB\MysqlDB $database
     * @param \Psr\Cache\CacheItemPoolInterface $cachePool
     * @return void
     */
    protected function loadConjuncts(LoggerInterface $logger, AmpersandApp $app, MysqlDB $database, CacheItemPoolInterface $cachePool)
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
     *
     * @param \Ampersand\Plugs\IfcPlugInterface $defaultPlug
     * @return void
     */
    protected function loadInterfaces(IfcPlugInterface $defaultPlug)
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
     *
     * @param \Ampersand\Plugs\ViewPlugInterface $defaultPlug
     * @return void
     */
    protected function loadViews(ViewPlugInterface $defaultPlug)
    {
        $allViewDefs = (array)json_decode(file_get_contents($this->modelFiles['views']), true);
        
        foreach ($allViewDefs as $viewDef) {
            $this->views[$viewDef['label']] = new View($viewDef, $defaultPlug);
        }

        $this->initialized[] = 'views';
    }

    /**
     * Import all rule definitions from json file and instantiate Rule objects
     *
     * @param \Ampersand\Plugs\ViewPlugInterface $defaultPlug
     * @param \Ampersand\AmpersandApp $app
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    protected function loadRules(ViewPlugInterface $defaultPlug, AmpersandApp $app, LoggerInterface $logger)
    {
        $this->rules = [];

        $allRuleDefs = (array) json_decode(file_get_contents($this->modelFiles['rules']), true);
        
        // Signal rules
        foreach ($allRuleDefs['signals'] as $ruleDef) {
            $rule = new Rule($ruleDef, $defaultPlug, 'signal', $app, $logger);
            $this->rules[$rule->getId()] = $rule;
        }
        
        // Invariant rules
        foreach ($allRuleDefs['invariants'] as $ruleDef) {
            $rule = new Rule($ruleDef, $defaultPlug, 'invariant', $app, $logger);
            $this->rules[$rule->getId()] = $rule;
        }

        $this->initialized[] = 'rules';
    }

    /**
     * Import all role definitions from json file and instantiate Role objects
     *
     * @return void
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
     * @return \Ampersand\Core\Population
     */
    public function getMetaPopulation(): Population
    {
        // Start with initial population
        $population = $this->getInitialPopulation();

        // Filter meta model atoms from initial population
        $conceptLabels = [
            ProtoContext::CPT_ROLE,
            ProtoContext::CPT_IFC,
            ProtoContext::CPT_LABEL
        ];
        $population = $population->filterAtoms(function (Atom $atom) use ($conceptLabels) {
            return in_array($atom->concept->getLabel(), $conceptLabels, true);
        });
        
        // Filter meta model links from initial population
        $relationSignatures = [
            ProtoContext::REL_ROLE_LABEL,
            ProtoContext::REL_IFC_LABEL,
            ProtoContext::REL_IFC_ROLES,
            ProtoContext::REL_IFC_IS_PUBLIC,
            ProtoContext::REL_IFC_IS_API
        ];
        $population = $population->filterLinks(function (Link $link) use ($relationSignatures) {
            return in_array($link->relation()->getSignature(), $relationSignatures, true);
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
            throw new Exception("Ampersand model is not yet initialized", 500);
        }
        
        return $this->concepts;
    }

    /**
     * Return concept object given a concept identifier
     *
     * @param string $conceptId escaped concept name
     * @throws \Exception if concept is not defined
     * @return \Ampersand\Core\Concept
     */
    public function getConcept(string $conceptId): Concept
    {
        if (!array_key_exists($conceptId, $concepts = $this->getAllConcepts())) {
            throw new Exception("Concept '{$conceptId}' is not defined", 500);
        }
         
        return $concepts[$conceptId];
    }
    
    /**
     * Return concept object given a concept label
     *
     * @param string $conceptLabel Unescaped concept name
     * @throws \Exception if concept is not defined
     * @return \Ampersand\Core\Concept
     */
    public function getConceptByLabel(string $conceptLabel): Concept
    {
        foreach ($this->getAllConcepts() as $concept) {
            /** @var \Ampersand\Core\Concept $concept */
            if ($concept->label === $conceptLabel) {
                return $concept;
            }
        }
        
        throw new Exception("Concept '{$conceptLabel}' is not defined", 500);
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
     * @throws \Exception when relations are not loaded (yet) because model is not initialized
     * @return \Ampersand\Core\Relation[]
     */
    public function getRelations(): array
    {
        if (!in_array('relations', $this->initialized)) {
            throw new Exception("Ampersand model is not yet initialized", 500);
        }
         
        return $this->relations;
    }

    /**
     * Return relation object
     *
     * @param string $relationSignature
     * @param \Ampersand\Core\Concept|null $srcConcept
     * @param \Ampersand\Core\Concept|null $tgtConcept
     *
     * @throws \Ampersand\Exception\RelationNotDefined if relation is not defined
     * @throws \Exception when something is wrong
     * @return \Ampersand\Core\Relation
     */
    public function getRelation($relationSignature, Concept $srcConcept = null, Concept $tgtConcept = null): Relation
    {
        if (!in_array('relations', $this->initialized)) {
            throw new Exception("Ampersand model is not yet initialized", 500);
        }
        
        // If relation can be found by its fullRelationSignature return the relation
        if (array_key_exists($relationSignature, $this->relations)) {
            $relation = $this->relations[$relationSignature];
            
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
        throw new RelationNotDefined("Relation '{$relationSignature}[{$srcConcept}*{$tgtConcept}]' is not defined", 500);
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
            throw new Exception("Ampersand model is not yet initialized", 500);
        }
        
        return $this->interfaces;
    }

    /**
     * Returns if interface exists
     * @var string $ifcId Identifier of interface
     * @return bool
     */
    public function interfaceExists(string $ifcId): bool
    {
        return array_key_exists($ifcId, $this->getAllInterfaces());
    }

    /**
     * Returns toplevel interface object
     * @param string $ifcId
     * @param bool $fallbackOnLabel if set to true, the param $ifcId may also contain an interface label (i.e. name as defined in &-script)
     * @throws \Ampersand\Exception\InterfaceNotDefined when interface does not exist
     * @return \Ampersand\Interfacing\Ifc
     */
    public function getInterface(string $ifcId, $fallbackOnLabel = false): Ifc
    {
        if (!array_key_exists($ifcId, $interfaces = $this->getAllInterfaces())) {
            if ($fallbackOnLabel) {
                return $this->getInterfaceByLabel($ifcId);
            } else {
                throw new InterfaceNotDefined("Interface '{$ifcId}' is not defined", 500);
            }
        }

        return $interfaces[$ifcId];
    }

    /**
     * Undocumented function
     *
     * @param string $ifcLabel
     * @throws \Ampersand\Exception\InterfaceNotDefined when interface does not exist
     * @return \Ampersand\Interfacing\Ifc
     */
    public function getInterfaceByLabel(string $ifcLabel): Ifc
    {
        foreach ($this->getAllInterfaces() as $interface) {
            /** @var \Ampersand\Interfacing\Ifc $interface */
            if ($interface->getLabel() === $ifcLabel) {
                return $interface;
            }
        }
        
        throw new InterfaceNotDefined("Interface with label '{$ifcLabel}' is not defined", 500);
    }

    /**********************************************************************************************
     * VIEWS
    **********************************************************************************************/
    /**
     * Returns array with all view objects
     *
     * @return \Ampersand\Interfacing\View[]
     */
    public function getAllViews()
    {
        if (!in_array('views', $this->initialized)) {
            throw new Exception("Ampersand model is not yet initialized", 500);
        }
         
        return $this->views;
    }

    /**
     * Return view object
     *
     * @param string $viewLabel
     * @throws \Exception if view is not defined
     * @return \Ampersand\Interfacing\View
     */
    public function getView($viewLabel): View
    {
        if (!in_array('views', $this->initialized)) {
            throw new Exception("Ampersand model is not yet initialized", 500);
        }

        if (!array_key_exists($viewLabel, $this->views)) {
            throw new Exception("View '{$viewLabel}' is not defined", 500);
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
            throw new Exception("Ampersand model is not yet initialized", 500);
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
                throw new Exception("Specified rule type is wrong", 500);
                break;
        }
    }

    /**
     * Get rule with a given rule name
     *
     * @param string $ruleName
     * @throws Exception if rule is not defined
     * @return \Ampersand\Rule\Rule
     */
    public function getRule($ruleName): Rule
    {
        if (!in_array('rules', $this->initialized)) {
            throw new Exception("Ampersand model is not yet initialized", 500);
        }

        if (!array_key_exists($ruleName, $this->rules)) {
            throw new Exception("Rule '{$ruleName}' is not defined", 500);
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
            throw new Exception("Ampersand model is not yet initialized", 500);
        }
         
        return $this->conjuncts;
    }

    /**
     * Return conjunct object
     *
     * @param string $conjId
     * @throws \Exception if conjunct is not defined
     * @return \Ampersand\Rule\Conjunct
     */
    public function getConjunct($conjId): Conjunct
    {
        if (!in_array('conjuncts', $this->initialized)) {
            throw new Exception("Ampersand model is not yet initialized", 500);
        }

        if (!array_key_exists($conjId, $this->conjuncts)) {
            throw new Exception("Conjunct '{$conjId}' is not defined", 500);
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
    public function getAllRoles()
    {
        if (!in_array('roles', $this->initialized)) {
            throw new Exception("Ampersand model is not yet initialized", 500);
        }
         
        return $this->roles;
    }

    /**
     * Return role object
     *
     * @param string $roleId
     * @return \Ampersand\Role
     */
    public function getRoleById(string $roleId): Role
    {
        foreach ($this->getAllRoles() as $role) {
            if ($role->getId() === $roleId) {
                return $role;
            }
        }
        
        throw new Exception("Role with id '{$roleId}' is not defined", 500);
    }
    
    /**
     * Return role object
     *
     * @param string $roleName
     * @return \Ampersand\Role
     */
    public function getRoleByName($roleName): Role
    {
        if (!array_key_exists($roleName, $roles = $this->getAllRoles())) {
            throw new Exception("Role '{$roleName}' is not defined", 500);
        }
    
        return $roles[$roleName];
    }
    
    /**********************************************************************************************
     * MISC
    **********************************************************************************************/
    /**
     * Write new checksum file of generated model
     *
     * @return void
     */
    public function writeChecksumFile()
    {
        /* Earlier implementation.
        $this->logger->debug("Writing checksum file for generated Ampersand model files");

        $checksums = [];
        foreach ($this->modelFiles as $path) {
            $filename = pathinfo($path, PATHINFO_BASENAME);
            $checksums[$filename] = hash_file(self::HASH_ALGORITHM, $path);
        }

        file_put_contents($this->checksumFile, serialize($checksums));
        */

        // Now: use the hash value from generated output (created by Haskell codebase)
        file_put_contents($this->checksumFile, $this->getSetting('compiler.modelHash'));
    }

    /**
     * Verify checksums of generated model. Return true when valid, false otherwise.
     *
     * @return bool
     */
    public function verifyChecksum(): bool
    {
        $this->logger->debug("Verifying checksum for Ampersand model files");

        return (file_get_contents($this->checksumFile) === $this->getSetting('compiler.modelHash'));

        /* Earlier implementation.
        $valid = true; // assume all checksums match

        // Get stored checksums
        $checkSums = unserialize(file_get_contents($this->checksumFile));

        // Compare checksum with actual file
        foreach ($this->modelFiles as $path) {
            $filename = pathinfo($path, PATHINFO_BASENAME);
            if ($checkSums[$filename] !== hash_file(self::HASH_ALGORITHM, $path)) {
                $this->logger->warning("Invalid checksum of file '{$filename}'");
                $valid = false;
            }
        }

        return $valid;
        */
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function getFilePath(string $filename): string
    {
        if (!array_key_exists($filename, $this->modelFiles)) {
            throw new Exception("File '{$filename}' is not part of the specified Ampersand model files", 500);
        }

        return $this->modelFiles[$filename];
    }

    protected function loadFile(string $filename)
    {
        $decoder = new JsonDecode(false);
        return $decoder->decode(file_get_contents($this->getFilePath($filename)), JsonEncoder::FORMAT);
    }

    protected function getFileContent(string $filename)
    {
        static $loadedFiles = [];

        if (!array_key_exists($filename, $loadedFiles)) {
            $loadedFiles[$filename] = $this->loadFile($filename);
        }

        return $loadedFiles[$filename];
    }

    protected function getSetting(string $setting)
    {
        $settings = $this->getFileContent('settings');
        
        if (!property_exists($settings, $setting)) {
            throw new Exception("Undefined setting '{$setting}'", 500);
        }

        return $settings->$setting;
    }
}
