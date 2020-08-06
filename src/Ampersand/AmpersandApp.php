<?php

namespace Ampersand;

use Ampersand\Misc\Settings;
use Ampersand\Model;
use Ampersand\Transaction;
use Ampersand\Plugs\StorageInterface;
use Ampersand\Plugs\ConceptPlugInterface;
use Ampersand\Plugs\RelationPlugInterface;
use Ampersand\Session;
use Ampersand\Core\Atom;
use Exception;
use Ampersand\Core\Concept;
use Ampersand\Rule\RuleEngine;
use Psr\Log\LoggerInterface;
use Ampersand\Log\Logger;
use Ampersand\Log\UserLogger;
use Ampersand\Core\Relation;
use Closure;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ampersand\Interfacing\Ifc;
use Ampersand\Plugs\MysqlDB\MysqlDB;
use Ampersand\Misc\Installer;
use Ampersand\Interfacing\ResourceList;

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
     * @var \Psr\EventDispatcher\EventDispatcherInterface
     */
    protected $eventDispatcher;

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
     * @var array<string,ConceptPlugInterface[]>
     */
    protected $customConceptPlugs = [];

    /**
     * List of custom plugs for relations
     * @var array<string,RelationPlugInterface[]>
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
    public function __construct(Model $model, Settings $settings, LoggerInterface $logger, EventDispatcherInterface $eventDispatcher)
    {
        $this->logger = $logger;
        $this->userLogger = new UserLogger($this, $logger);
        $this->model = $model;
        $this->settings = $settings;
        $this->eventDispatcher = $eventDispatcher;

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

    public function eventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function init(): AmpersandApp
    {
        try {
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
            $this->model->init($this);

            // Add concept plugs
            foreach ($this->model->getAllConcepts() as $cpt) {
                /** @var \Ampersand\Core\Concept $cpt */
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

    public function getConjunctCache(): CacheItemPoolInterface
    {
        return $this->conjunctCache;
    }

    public function setConjunctCache(CacheItemPoolInterface $cache): void
    {
        $this->conjunctCache = $cache;
    }

    public function setSession(Atom $sessionAccount = null): AmpersandApp
    {
        $this->session = new Session($this->logger, $this);
        $this->session->initSessionAtom();
        if (isset($sessionAccount)) {
            $this->session->setSessionAccount($sessionAccount);
        }
        
        // Run exec engine and close transaction
        $this->getCurrentTransaction()->runExecEngine()->close();

        // Set accessible interfaces and rules to maintain
        $this->setAccessibleInterfaces()->setRulesToMaintain();

        return $this;
    }

    public function resetSession(Atom $sessionAccount = null)
    {
        $this->logger->debug("Resetting session");
        $this->session->deleteSessionAtom(); // delete Ampersand representation of session
        Session::resetPhpSessionId();
        $this->setSession($sessionAccount);
    }

    protected function setRulesToMaintain(): AmpersandApp
    {
        // Reset
        $this->rulesToMaintain = [];

        // Add rules for all active session roles
        foreach ($this->getActiveRoles() as $roleAtom) {
            /** @var \Ampersand\Core\Atom $roleAtom */

            // Set rules to maintain
            try {
                $role = $this->model->getRoleById($roleAtom->getId());
                $this->rulesToMaintain = array_merge($this->rulesToMaintain, $role->maintains());
            } catch (Exception $e) {
                $this->logger->debug("Actived role '{$roleAtom}', but role is not used/defined in &-script.");
            }
        }
        
        // Remove duplicates
        $this->rulesToMaintain = array_unique($this->rulesToMaintain);

        return $this;
    }

    protected function setAccessibleInterfaces(): AmpersandApp
    {
        // Reset
        $this->accessibleInterfaces = [];
        $ifcAtoms = [];

        $settingKey = 'rbac.accessibleInterfacesIfcId';
        $rbacIfcId = $this->getSettings()->get($settingKey);
        
        // Get accessible interfaces using defined INTERFACE
        if (!is_null($rbacIfcId)) {
            if (!$this->model->interfaceExists($rbacIfcId)) {
                throw new Exception("Specified interface '{$rbacIfcId}' in setting '{$settingKey}' does not exist", 500);
            }
            
            $rbacIfc = $this->model->getInterface($rbacIfcId);

            // Check for the right SRC and TGT concepts
            if ($rbacIfc->getSrcConcept() !== $this->model->getSessionConcept()) {
                throw new Exception("Src concept of interface '{$rbacIfcId}' in setting '{$settingKey}' MUST be {$this->model->getSessionConcept()->getId()}", 500);
            }
            if ($rbacIfc->getTgtConcept() !== $this->model->getInterfaceConcept()) {
                throw new Exception("Tgt concept of interface '{$rbacIfcId}' in setting '{$settingKey}' MUST be {$this->model->getInterfaceConcept()->getId()}", 500);
            }

            $this->logger->debug("Getting accessible interfaces using INTERFACE {$rbacIfc->getId()}");
            
            $ifcAtoms = ResourceList::makeFromInterface($this->session->getId(), $rbacIfc->getId())->getResources();
        
        // Else query the RELATION pf_ifcRoles[PF_Interface*Role] for every active role
        } else {
            foreach ($this->getActiveRoles() as $roleAtom) {
                /** @var \Ampersand\Core\Atom $roleAtom */
                
                // Query accessible interfaces
                $ifcAtoms = array_merge($ifcAtoms, $roleAtom->getTargetAtoms('pf_ifcRoles[PF_Interface*Role]', true));
            }
        }

        // Filter (un)defined interfaces
        $ifcAtoms = array_filter(
            $ifcAtoms,
            function (Atom $ifcAtom) {
                if ($this->model->interfaceExists($ifcAtom->getId())) {
                    return true;
                } else {
                    $this->logger->warning("Interface id '{$ifcAtom->getId()}' specified as accessible interface, but this interface is not defined");
                    return false;
                }
            }
        );

        // Map ifcAtoms to Ifc objects
        $this->accessibleInterfaces = array_map(function (Atom $ifcAtom) {
            return $this->model->getInterface($ifcAtom->getId());
        }, $ifcAtoms);

        // Remove duplicates
        $this->accessibleInterfaces = array_unique($this->accessibleInterfaces);

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
        $this->resetSession($account);

        // Run ExecEngine to populate session related relations (e.g. sessionAllowedRoles)
        $transaction = $this->getCurrentTransaction()->runExecEngine();

        // Activate all allowed roles by default
        foreach ($this->session->getSessionAllowedRoles() as $atom) {
            $this->session->toggleActiveRole($atom, true);
        }

        // Run ExecEngine and close transaction
        $transaction->runExecEngine()->close();

        // Set (new) interfaces and rules
        $this->setAccessibleInterfaces()->setRulesToMaintain();
    }

    /**
     * Logout user, destroy and reset session
     *
     * @return void
     */
    public function logout(): void
    {
        // Renew session. See OWASP session management cheat sheet
        $this->resetSession();

        // Run exec engine and close transaction
        $this->getCurrentTransaction()->runExecEngine()->close();

        $this->setAccessibleInterfaces()->setRulesToMaintain();
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

        // Increase timeout to at least 5 min
        if ($this->getSettings()->get('global.scriptTimeout') < 300) {
            set_time_limit(300);
            $this->logger->debug('PHP script timeout increased to 300 seconds');
        }

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
        foreach ($this->model->getAllConcepts() as $cpt) {
            /** @var \Ampersand\Core\Concept $cpt */
            $cpt->clearAtomCache(); // local cache in Ampersand code
        }

        $transaction = $this->newTransaction();
        $installer = new Installer($this->logger);

        // Metapopulation and navigation menus
        try {
            $installer->reinstallMetaPopulation($this->getModel());
            if (!$transaction->runExecEngine()->checkInvariantRules()) {
                $this->logger->warning("Invariant rules do not hold for meta population");
            }

            $installer->reinstallNavigationMenus($this->getModel());
            if (!$transaction->runExecEngine()->checkInvariantRules()) {
                $this->logger->warning("Invariant rules do not hold for meta population and/or navigation menu");
            }
        } catch (Exception $e) {
            throw new Exception("Error while installing metapopulation and navigation menus: {$e->getMessage()}", 500, $e);
        }

        // Initial population
        if ($installDefaultPop) {
            $installer->addInitialPopulation($this->getModel());
        } else {
            $this->logger->info("Skip initial population");
        }

        // Close transaction
        $transaction->runExecEngine()->close(false, $ignoreInvariantRules);
        if ($transaction->isRolledBack()) {
            throw new Exception("Initial installation does not satisfy invariant rules. See log files", 500);
        }

        // Evaluate all conjunct and save cache
        $this->logger->info("Initial evaluation of all conjuncts after application reinstallation");
        foreach ($this->model->getAllConjuncts() as $conj) {
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
            // Set sessionActiveRoles[SESSION*Role]
            $this->session->toggleActiveRole($this->model->getRoleConcept()->makeAtom($role->id), $role->active);
        }
        
        // Commit transaction (exec-engine kicks also in)
        $this->getCurrentTransaction()->runExecEngine()->close();

        $this->setAccessibleInterfaces()->setRulesToMaintain();
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
                    && (!$ifcObj->crudC() or ($ifcObj->crudU() or $ifcObj->crudD())) // exclude CRud pattern
                    && !$ifc->isAPI(); // don't include API interfaces
        });
    }

    /**
     * Determine if provided concept is editable concept in one of the accessible interfaces in the current session
     * @param \Ampersand\Core\Concept $concept
     * @return bool
     */
    public function isEditableConcept(Concept $concept): bool
    {
        return array_reduce($this->accessibleInterfaces, function (bool $carry, Ifc $ifc) use ($concept) {
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
        foreach (RuleEngine::getViolationsFromCache($this->getConjunctCache(), $this->rulesToMaintain) as $violation) {
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
     * @throws \Ampersand\Exception\RelationNotDefined if relation is not defined
     * @return \Ampersand\Core\Relation
     */
    public function getRelation($relationSignature, Concept $srcConcept = null, Concept $tgtConcept = null): Relation
    {
        return $this->model->getRelation($relationSignature, $srcConcept, $tgtConcept);
    }
}
