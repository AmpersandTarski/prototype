<?php

namespace Ampersand;

use Ampersand\Misc\Settings;
use Ampersand\Model;
use Ampersand\Transaction;
use Ampersand\Plugs\StorageInterface;
use Ampersand\Plugs\ConceptPlugInterface;
use Ampersand\Plugs\RelationPlugInterface;
use Ampersand\Rule\Conjunct;
use Ampersand\Session;
use Ampersand\Core\Atom;
use Exception;
use Ampersand\Core\Concept;
use Ampersand\Role;
use Ampersand\Rule\RuleEngine;
use Psr\Log\LoggerInterface;
use Ampersand\Log\Logger;
use Ampersand\Log\UserLogger;
use Ampersand\Core\Relation;
use Ampersand\Interfacing\View;
use Closure;
use Psr\Cache\CacheItemPoolInterface;
use Ampersand\Interfacing\Ifc;
use Ampersand\Plugs\MysqlDB\MysqlDB;
use Ampersand\Misc\Installer;

class AmpersandApp
{
    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * User logger (i.e. logs are returned to user)
     *
     * @var \Ampersand\Log\UserLogger
     */
    protected $userLogger;

    /**
     * Ampersand application name (i.e. CONTEXT of ADL entry script)
     *
     * @var string
     */
    protected $name;

    /**
     * Reference to generated Ampersand model
     *
     * @var \Ampersand\Model
     */
    protected $model;

    /**
     * Settings object
     *
     * @var \Ampersand\Misc\Settings
     */
    protected $settings;

    /**
     * List with storages that are registered for this application
     * @var \Ampersand\Plugs\StorageInterface[]
     */
    protected $storages = [];

    /**
     * Default storage plug
     * @var \Ampersand\Plugs\MysqlDB\MysqlDB
     */
    protected $defaultStorage = null;

    /**
     * Cache implementation for conjunct violation cache
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    protected $conjunctCache = null;

    /**
     * List of custom plugs for concepts
     * @var array[string]\Ampersand\Plugs\ConceptPlugInterface[]
     */
    protected $customConceptPlugs = [];

    /**
     * List of custom plugs for relations
     * @var array[string]\Ampersand\Plugs\RelationPlugInterface[]
     */
    protected $customRelationPlugs = [];

    /**
     * List with anonymous functions (closures) to be executed during initialization
     * (i.e. during AmpersandApp::init())
     *
     * @var \Closure[]
     */
    protected $initClosures = [];

    /**
     * The session between AmpersandApp and user
     *
     * @var \Ampersand\Session
     */
    protected $session = null;

    /**
     * List of accessible interfaces for the user of this Ampersand application
     *
     * @var \Ampersand\Interfacing\Ifc[]
     */
    protected $accessibleInterfaces = [];
    
    /**
     * List with rules that are maintained by the activated roles in this Ampersand application
     *
     * @var \Ampersand\Rule\Rule[] $rulesToMaintain
     */
    protected $rulesToMaintain = []; // rules that are maintained by active roles

    /**
     * List of all transactions (open and closed)
     *
     * @var \Ampersand\Transaction[]
     */
    protected $transactions = [];
    
    /**
     * Constructor
     *
     * @param \Ampersand\Model $model
     * @param \Ampersand\Misc\Settings $settings
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(Model $model, Settings $settings, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->userLogger = new UserLogger($logger);
        $this->model = $model;
        $this->settings = $settings;

        // Set app name
        $this->name = $this->settings->get('global.contextName');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function userLog(): UserLogger
    {
        return $this->userLogger;
    }

    public function init(): AmpersandApp
    {
        try {
            $scriptStartTime = microtime(true);

            $this->logger->info('Initialize Ampersand application');

            // Check checksum
            if (!$this->model->verifyChecksum() && !$this->settings->get('global.productionEnv')) {
                $this->userLogger->warning("Generated model is changed. You SHOULD reinstall your application");
            }

            // Check for default storage plug
            if (!in_array($this->defaultStorage, $this->storages)) {
                throw new Exception("No default storage plug registered", 500);
            }

            // Check for conjunct cache
            if (is_null($this->conjunctCache)) {
                throw new Exception("No conjunct cache implementaion registered", 500);
            }

            // Initialize storage plugs
            foreach ($this->storages as $storagePlug) {
                $storagePlug->init();
            }

            // Initialize Ampersand model (i.e. load all defintions from generated json files)
            

            // Instantiate object definitions from generated files
            $genericsFolder = $this->model->getFolder() . '/';
            Conjunct::setAllConjuncts($genericsFolder . 'conjuncts.json', Logger::getLogger('RULEENGINE'), $this, $this->defaultStorage, $this->conjunctCache);
            View::setAllViews($genericsFolder . 'views.json', $this->defaultStorage);
            Concept::setAllConcepts($genericsFolder . 'concepts.json', Logger::getLogger('CORE'), $this);
            $this->model->init($this);
            Role::setAllRoles($genericsFolder . 'roles.json', $this->model);

            // Add concept plugs
            foreach (Concept::getAllConcepts() as $cpt) {
                if (array_key_exists($cpt->label, $this->customConceptPlugs)) {
                    foreach ($this->customConceptPlugs[$cpt->label] as $plug) {
                        $cpt->addPlug($plug);
                    }
                } else {
                    $cpt->addPlug($this->defaultStorage);
                }
            }

            // Add relation plugs
            foreach ($this->model->getRelations() as $rel) {
                /** @var \Ampersand\Core\Relation $rel */
                if (array_key_exists($rel->signature, $this->customRelationPlugs)) {
                    foreach ($this->customRelationPlugs[$rel->signature] as $plug) {
                        $rel->addPlug($plug);
                    }
                } else {
                    $rel->addPlug($this->defaultStorage);
                }
            }

            // Run registered initialization closures
            foreach ($this->initClosures as $closure) {
                $closure->call($this);
            }

            // Log performance
            $executionTime = round(microtime(true) - $scriptStartTime, 2);
            $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2); // Mb
            Logger::getLogger('PERFORMANCE')->debug("PHASE-2 INIT: Memory in use: {$memoryUsage} Mb");
            Logger::getLogger('PERFORMANCE')->debug("PHASE-2 INIT: Execution time  : {$executionTime} Sec");

            return $this;
        } catch (\Ampersand\Exception\NotInstalledException $e) {
            throw $e;
        }
    }

    /**
     * Add closure to be executed during initialization of Ampersand application
     *
     * @param \Closure $closure
     * @return void
     */
    public function registerInitClosure(Closure $closure): void
    {
        $this->initClosures[] = $closure;
    }
    
    public function registerStorage(StorageInterface $storage): void
    {
        if (!in_array($storage, $this->storages)) {
            $this->logger->debug("Add storage: " . $storage->getLabel());
            $this->storages[] = $storage;
        }
    }

    public function registerCustomConceptPlug(string $conceptLabel, ConceptPlugInterface $plug): void
    {
        $this->customConceptPlugs[$conceptLabel][] = $plug;
        $this->registerStorage($plug);
    }

    public function registerCustomRelationPlug(string $relSignature, RelationPlugInterface $plug): void
    {
        $this->customRelationPlugs[$relSignature][] = $plug;
        $this->registerStorage($plug);
    }

    public function getDefaultStorage(): MysqlDB
    {
        return $this->defaultStorage;
    }

    /**
     * Set default storage.
     * For know we only support a MysqlDB as default storage.
     * Ampersand generator outputs a SQL (construct) query for each concept, relation, interface-, view- and conjunct expression
     *
     * @param \Ampersand\Plugs\MysqlDB\MysqlDB $storage
     * @return void
     */
    public function setDefaultStorage(MysqlDB $storage): void
    {
        $this->defaultStorage = $storage;
        $this->registerStorage($storage);
    }

    public function setConjunctCache(CacheItemPoolInterface $cache): void
    {
        $this->conjunctCache = $cache;
    }

    public function setSession(): AmpersandApp
    {
        $scriptStartTime = microtime(true);

        $this->session = new Session($this->logger, $this);

        // Run exec engine and close transaction
        $this->getCurrentTransaction()->runExecEngine()->close();

        // Set accessible interfaces and rules to maintain
        $this->setInterfacesAndRules();

        // Log performance
        $executionTime = round(microtime(true) - $scriptStartTime, 2);
        $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2); // Mb
        Logger::getLogger('PERFORMANCE')->debug("PHASE-3 SESSION: Memory in use: {$memoryUsage} Mb");
        Logger::getLogger('PERFORMANCE')->debug("PHASE-3 SESSION: Execution time  : {$executionTime} Sec");

        return $this;
    }

    protected function setInterfacesAndRules(): AmpersandApp
    {
        // Add public interfaces
        $this->accessibleInterfaces = $this->model->getPublicInterfaces();

        // Add interfaces and rules for all active session roles
        foreach ($this->getActiveRoles() as $roleAtom) {
            /** @var \Ampersand\Core\Atom $roleAtom */
            try {
                $role = Role::getRoleByName($roleAtom->getId());
                $this->accessibleInterfaces = array_merge($this->accessibleInterfaces, $role->interfaces());
                $this->rulesToMaintain = array_merge($this->rulesToMaintain, $role->maintains());
            } catch (Exception $e) {
                $this->logger->debug("Actived role '{$roleAtom}', but role is not used/defined in &-script.");
            }
        }

        // Remove duplicates
        $this->accessibleInterfaces = array_unique($this->accessibleInterfaces);
        $this->rulesToMaintain = array_unique($this->rulesToMaintain);

        return $this;
    }

    /**
     * Get the session object for this instance of the ampersand application
     *
     * @return \Ampersand\Session
     */
    public function getSession(): Session
    {
        if (is_null($this->session)) {
            throw new Exception("Session not yet initialized", 500);
        }
        return $this->session;
    }

    /**
     * Get Ampersand model for this application
     *
     * @return \Ampersand\Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get settings object for this application
     *
     * @return \Ampersand\Misc\Settings
     */
    public function getSettings(): Settings
    {
        return $this->settings;
    }

    /**
     * Get list of accessible interfaces for the user of this Ampersand application
     *
     * @return \Ampersand\Interfacing\Ifc[]
     */
    public function getAccessibleInterfaces(): array
    {
        return $this->accessibleInterfaces;
    }

    /**********************************************************************************************
     * TRANSACTIONS
     **********************************************************************************************/
    /**
     * Open new transaction.
     * Note! Make sure that a open transaction is closed first
     *
     * @return \Ampersand\Transaction
     */
    public function newTransaction(): Transaction
    {
        return $this->transactions[] = new Transaction($this, Logger::getLogger('TRANSACTION'));
    }
    
    /**
     * Return current open transaction or open new transactions
     *
     * @return \Ampersand\Transaction
     */
    public function getCurrentTransaction(): Transaction
    {
        // Check and return if there is a open transaction
        foreach ($this->transactions as $transaction) {
            if ($transaction->isOpen()) {
                return $transaction;
            }
        }
        return $this->newTransaction();
    }

    /**
     * Return list of all transactions in this app (open and closed)
     *
     * @return \Ampersand\Transaction[]
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**********************************************************************************************
     * OTHER
     **********************************************************************************************/

    /**
     * Login user and commit transaction
     *
     * @return void
     */
    public function login(Atom $account): void
    {
        // Renew session. See topic 'Renew the Session ID After Any Privilege Level Change' in OWASP session management cheat sheet
        $this->session->reset();

        // Set sessionAccount
        $this->session->setSessionAccount($account);

        // Run ExecEngine to populate session related relations (e.g. sessionAllowedRoles)
        $transaction = $this->getCurrentTransaction()->runExecEngine();

        // Activate all allowed roles by default
        foreach ($this->session->getSessionAllowedRoles() as $atom) {
            $this->session->toggleActiveRole($atom, true);
        }

        // Run ExecEngine and close transaction
        $transaction->runExecEngine()->close();

        // Set (new) interfaces and rules
        $this->setInterfacesAndRules();
    }

    /**
     * Logout user, destroy and reset session
     *
     * @return void
     */
    public function logout(): void
    {
        // Renew session. See OWASP session management cheat sheet
        $this->session->reset();

        // Run exec engine and close transaction
        $this->getCurrentTransaction()->runExecEngine()->close();

        $this->setInterfacesAndRules();
    }

    /**
     * Function to reinstall the application. This includes database structure and load default population
     *
     * @param bool $installDefaultPop specifies whether or not to install the default population
     * @param bool $ignoreInvariantRules
     * @return \Ampersand\AmpersandApp $this
     */
    public function reinstall(bool $installDefaultPop = true, bool $ignoreInvariantRules = false): AmpersandApp
    {
        $this->logger->info("Start application reinstall");

        // Clear notifications
        $this->userLogger->clearAll();

        // Write new checksum file of generated Ampersand moel
        $this->model->writeChecksumFile();

        // Call reinstall method on every registered storage (e.g. for MysqlDB implementation this means (re)creating database structure)
        foreach ($this->storages as $storage) {
            $storage->reinstallStorage($this->model);
        }

        $this->init();

        // Clear caches
        $this->conjunctCache->clear(); // external cache item pool
        foreach (Concept::getAllConcepts() as $cpt) {
            $cpt->clearAtomCache(); // local cache in Ampersand code
        }

        $installer = new Installer($this, $this->logger);

        // Meta population and navigation menus
        try {
            $installer->reinstallMetaPopulation()->reinstallNavigationMenus();
        } catch (Exception $e) {
            throw new Exception("Error in installing meta population: {$e->getMessage()}", 500, $e);
        }

        // Initial population
        if ($installDefaultPop) {
            $installer->addInitialPopulation($this->model, $ignoreInvariantRules);
        } else {
            $this->logger->info("Skip initial population");
        }

        // Evaluate all conjunct and save cache
        $this->logger->info("Initial evaluation of all conjuncts after application reinstallation");
        foreach (Conjunct::getAllConjuncts() as $conj) {
            /** @var \Ampersand\Rule\Conjunct $conj */
            $conj->evaluate()->persistCacheItem();
        }

        $this->userLogger->notice("Application successfully reinstalled");
        $this->logger->info("End application reinstall");

        return $this;
    }

    /**
     * (De)activate session roles
     *
     * @param array $roles
     * @return void
     */
    public function setActiveRoles(array $roles): void
    {
        foreach ($roles as $role) {
            // Set sessionActiveRoles[SESSION*PF_Role]
            $this->session->toggleActiveRole(Concept::getRoleConcept()->makeAtom($role->label), $role->active);
        }
        
        // Commit transaction (exec-engine kicks also in)
        $this->getCurrentTransaction()->runExecEngine()->close();

        $this->setInterfacesAndRules();
    }

    /**
     * Get allowed roles
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getAllowedRoles(): array
    {
        return $this->session->getSessionAllowedRoles();
    }

    /**
     * Get active roles
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getActiveRoles(): array
    {
        static $checkedTransactionIndex = null;
        static $activeRoles = [];

        $keys = array_keys($this->transactions);
        $lastTransactionIndex = end($keys); // TODO: as of php 7.3 array_key_last is introduced
        if (is_null($checkedTransactionIndex) || $checkedTransactionIndex !== $lastTransactionIndex) {
            $checkedTransactionIndex = $lastTransactionIndex;
            return $activeRoles = $this->session->getSessionActiveRoles();
        } else {
            $this->logger->debug("Active roles already evaluated. Returning from cache");
            return $activeRoles;
        }
    }

    /**
     * Get session roles with their id, label and state (active or not)
     *
     * @return array
     */
    public function getSessionRoles(): array
    {
        $activeRoleIds = array_map(function (Atom $role) {
            return $role->getId();
        }, $this->getActiveRoles());
        
        return array_map(function (Atom $roleAtom) use ($activeRoleIds) {
            return (object) ['id' => $roleAtom->getId()
                            ,'label' => $roleAtom->getLabel()
                            ,'active' => in_array($roleAtom->getId(), $activeRoleIds)
                            ];
        }, $this->getAllowedRoles());
    }

    /**
     * Check if session has any of the provided roles
     *
     * @param string[]|null $roles
     * @return bool
     */
    public function hasRole(array $roles = null): bool
    {
        // If provided roles is null (i.e. NOT empty array), then true
        if (is_null($roles)) {
            return true;
        }

        // Check for allowed roles
        return array_reduce($this->getAllowedRoles(), function (bool $carry, Atom $role) use ($roles) {
            return in_array($role->getId(), $roles) || $carry;
        }, false);
    }

    /**
     * Check if session has any of the provided roles active
     *
     * @param string[]|null $roles
     * @return bool
     */
    public function hasActiveRole(array $roles = null): bool
    {
        // If provided roles is null (i.e. NOT empty array), then true
        if (is_null($roles)) {
            return true;
        }

        // Check for active roles
        return array_reduce($this->getActiveRoles(), function (bool $carry, Atom $role) use ($roles) {
            return in_array($role->getId(), $roles) || $carry;
        }, false);
    }

    /**
     * Get interfaces that are accessible in the current session to 'Read' a certain concept
     *
     * @param \Ampersand\Core\Concept $cpt
     * @return \Ampersand\Interfacing\Ifc[]
     */
    public function getInterfacesToReadConcept(Concept $cpt): array
    {
        return array_filter($this->accessibleInterfaces, function (Ifc $ifc) use ($cpt) {
            $ifcObj = $ifc->getIfcObject();
            return $ifc->getSrcConcept()->hasSpecialization($cpt, true)
                    && $ifcObj->crudR()
                    && (!$ifcObj->crudC() or ($ifcObj->crudU() or $ifcObj->crudD()) // exclude CRud pattern
                    && !$ifc->isAPI()); // don't include API interfaces
        });
    }

    /**
     * Determine if provided concept is editable concept in one of the accessible interfaces in the current session
     * @param \Ampersand\Core\Concept $concept
     * @return bool
     */
    public function isEditableConcept(Concept $concept): bool
    {
        return array_reduce($this->accessibleInterfaces, function ($carry, Ifc $ifc) use ($concept) {
            $ifcObj = $ifc->getIfcObject();
            return ($carry || in_array($concept, $ifcObj->getEditableConcepts()));
        }, false);
    }
    
    /**
     * Determine if provided interface is accessible in the current session
     * @param \Ampersand\Interfacing\Ifc $ifc
     * @return bool
     */
    public function isAccessibleIfc(Ifc $ifc): bool
    {
        return in_array($ifc, $this->accessibleInterfaces, true);
    }

    /**
     * Evaluate and signal violations for all rules that are maintained by the activated roles
     *
     * @return void
     */
    public function checkProcessRules(): void
    {
        $activeRoleIds = array_map(function (Atom $role) {
            return $role->getId();
        }, $this->getActiveRoles());

        $this->logger->debug("Checking process rules for active roles: " . implode(', ', $activeRoleIds));
        
        // Check rules and signal notifications for all violations
        foreach (RuleEngine::getViolationsFromCache($this->rulesToMaintain) as $violation) {
            $this->userLogger->signal($violation);
        }
    }
    
    /**********************************************************************************************
     * SHORT CUTS
     **********************************************************************************************/
    /**
     * Return relation object
     *
     * @param string $relationSignature
     * @param \Ampersand\Core\Concept|null $srcConcept
     * @param \Ampersand\Core\Concept|null $tgtConcept
     *
     * @throws \Exception if relation is not defined
     * @return \Ampersand\Core\Relation
     */
    public function getRelation($relationSignature, Concept $srcConcept = null, Concept $tgtConcept = null): Relation
    {
        return $this->model->getRelation($relationSignature, $srcConcept, $tgtConcept);
    }
}
