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
use Ampersand\Event\TransactionEvent;
use Ampersand\Exception\AmpersandException;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\InvalidConfigurationException;
use Ampersand\Exception\MetaModelException;
use Ampersand\Exception\NotDefined\NotDefinedException;
use Ampersand\Frontend\FrontendInterface;
use Closure;
use Psr\Cache\CacheItemPoolInterface;
use Ampersand\Interfacing\Ifc;
use Ampersand\Plugs\MysqlDB\MysqlDB;
use Ampersand\Misc\Installer;
use Ampersand\Interfacing\ResourceList;
use League\Flysystem\Filesystem;
use Ampersand\Misc\ProtoContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AmpersandApp
{
    protected LoggerInterface $logger;

    /**
     * User logger (i.e. logs are returned to user)
     */
    protected UserLogger $userLogger;

    protected Filesystem $fileSystem;

    protected EventDispatcherInterface $eventDispatcher;

    /**
     * Ampersand application name (i.e. CONTEXT of ADL entry script)
     */
    protected string $name;

    /**
     * Reference to frontend implementation (e.g. AngularJSApp)
     */
    protected FrontendInterface $frontend;

    /**
     * Reference to generated Ampersand model
     */
    protected Model $model;

    /**
     * Settings object
     */
    protected Settings $settings;

    /**
     * List with storages that are registered for this application
     * @var \Ampersand\Plugs\StorageInterface[]
     */
    protected array $storages = [];

    /**
     * Default storage plug
     */
    protected ?MysqlDB $defaultStorage = null;

    /**
     * Cache implementation for conjunct violation cache
     */
    protected ?CacheItemPoolInterface $conjunctCache = null;

    /**
     * List of custom plugs for concepts
     * @var array<string,\Ampersand\Plugs\ConceptPlugInterface[]>
     */
    protected array $customConceptPlugs = [];

    /**
     * List of custom plugs for relations
     * @var array<string,\Ampersand\Plugs\RelationPlugInterface[]>
     */
    protected $customRelationPlugs = [];

    /**
     * List with anonymous functions (closures) to be executed during initialization
     * (i.e. during AmpersandApp::init())
     *
     * @var \Closure[]
     */
    protected array $initClosures = [];

    /**
     * The session between AmpersandApp and user
     */
    protected ?Session $session = null;

    /**
     * List of accessible interfaces for the user of this Ampersand application
     *
     * @var \Ampersand\Interfacing\Ifc[]
     */
    protected array $accessibleInterfaces = [];
    
    /**
     * List with rules that are maintained by the activated roles in this Ampersand application
     *
     * @var \Ampersand\Rule\Rule[] $rulesToMaintain
     */
    protected array $rulesToMaintain = []; // rules that are maintained by active roles

    /**
     * List of all transactions (open and closed)
     *
     * @var \Ampersand\Transaction[]
     */
    protected array $transactions = [];
    
    /**
     * Constructor
     */
    public function __construct(
        Model $model,
        Settings $settings,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        // FilesystemInterface $fileSystem
        League\Flysystem\Filesystem $fileSystem
    ) {
        $this->logger = $logger;
        $this->userLogger = new UserLogger($this, $logger);
        $this->model = $model;
        $this->settings = $settings;
        $this->eventDispatcher = $eventDispatcher;
        $this->fileSystem = $fileSystem;

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

    public function fileSystem(): Filesystem
    {
        return $this->fileSystem;
    }

    public function setFileSystem(Filesystem $fs): self
    {
        $this->fileSystem = $fs;
        return $this;
    }

    public function frontend(): FrontendInterface
    {
        return $this->frontend;
    }

    public function setFrontend(FrontendInterface $frontend): self
    {
        $this->frontend = $frontend;
        return $this;
    }

    public function inProductionMode(): bool
    {
        return $this->settings->get('global.productionEnv', true);
    }

    public function init(): self
    {
        $this->logger->info('Initialize Ampersand application');

        // Check for default storage plug
        if (!in_array($this->defaultStorage, $this->storages)) {
            throw new NotDefinedException("No default storage plug registered");
        }

        // Check for conjunct cache
        if (is_null($this->conjunctCache)) {
            throw new NotDefinedException("No conjunct cache implementaion registered");
        }

        // Initialize storage plugs
        foreach ($this->storages as $storagePlug) {
            $storagePlug->init();
        }

        // Initialize Ampersand model (i.e. load all defintions from generated json files)
        $this->model->init($this);

        // Add concept plugs
        foreach ($this->model->getAllConcepts() as $cpt) {
            if (array_key_exists($cpt->label, $this->customConceptPlugs)) {
                foreach ($this->customConceptPlugs[$cpt->label] as $plug) {
                    $cpt->addPlug($plug);
                }
            } else {
                $cpt->addPlug($this->defaultStorage); // @phan-suppress-current-line PhanTypeMismatchArgumentSuperType
            }
        }

        // Add relation plugs
        foreach ($this->model->getRelations() as $rel) {
            if (array_key_exists($rel->signature, $this->customRelationPlugs)) {
                foreach ($this->customRelationPlugs[$rel->signature] as $plug) {
                    $rel->addPlug($plug);
                }
            } else {
                $rel->addPlug($this->defaultStorage); // @phan-suppress-current-line PhanTypeMismatchArgumentSuperType
            }
        }

        // Run registered initialization closures
        foreach ($this->initClosures as $closure) {
            $closure->call($this);
        }

        return $this;
    }

    /**
     * Add closure to be executed during initialization of Ampersand application
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
        if (is_null($this->defaultStorage)) {
            throw new NotDefinedException("Default storage not set for Ampersand app");
        }

        return $this->defaultStorage;
    }

    /**
     * Set default storage.
     * For know we only support a MysqlDB as default storage.
     * Ampersand generator outputs a SQL (construct) query for each concept, relation, interface-, view- and conjunct expression
     */
    public function setDefaultStorage(MysqlDB $storage): void
    {
        $this->defaultStorage = $storage;
        $this->registerStorage($storage);
    }

    public function getConjunctCache(): CacheItemPoolInterface
    {
        if (is_null($this->conjunctCache)) {
            throw new NotDefinedException("Conjunct cache not set for Ampersand app");
        }

        return $this->conjunctCache;
    }

    public function setConjunctCache(CacheItemPoolInterface $cache): void
    {
        $this->conjunctCache = $cache;
    }

    public function setSession(?Atom $sessionAccount = null): self
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

    public function resetSession(?Atom $sessionAccount = null): void
    {
        $this->logger->debug("Resetting session");
        $this->session->deleteSessionAtom(); // delete Ampersand representation of session
        Session::resetPhpSessionId();
        $this->setSession($sessionAccount);
    }

    protected function setRulesToMaintain(): self
    {
        // Reset
        $this->rulesToMaintain = [];

        // Add rules for all active session roles
        foreach ($this->getActiveRoles() as $roleAtom) {
            // Set rules to maintain
            try {
                $role = $this->model->getRoleByName($roleAtom->getId());
                $this->rulesToMaintain = array_merge($this->rulesToMaintain, $role->maintains());
            } catch (Exception $e) {
                $this->logger->debug("Actived role '{$roleAtom}', but role is not used/defined in &-script.");
            }
        }
        
        // Remove duplicates
        $this->rulesToMaintain = array_unique($this->rulesToMaintain);

        return $this;
    }

    protected function setAccessibleInterfaces(): self
    {
        // Reset
        $this->accessibleInterfaces = [];
        $ifcAtoms = [];

        $settingKey = 'rbac.accessibleInterfacesIfcId';
        $rbacIfcId = $this->getSettings()->get($settingKey);
        
        // Get accessible interfaces using defined INTERFACE
        if (!is_null($rbacIfcId)) {
            if (!$this->model->interfaceExists($rbacIfcId)) {
                throw new InvalidConfigurationException("Specified interface '{$rbacIfcId}' in setting '{$settingKey}' does not exist");
            }
            
            $rbacIfc = $this->model->getInterface($rbacIfcId);

            // Check for the right SRC and TGT concepts
            if ($rbacIfc->getSrcConcept() !== $this->model->getSessionConcept()) {
                throw new MetaModelException("Src concept of interface '{$rbacIfcId}' in setting '{$settingKey}' MUST be {$this->model->getSessionConcept()->getId()}");
            }
            if ($rbacIfc->getTgtConcept() !== $this->model->getInterfaceConcept()) {
                throw new MetaModelException("Tgt concept of interface '{$rbacIfcId}' in setting '{$settingKey}' MUST be {$this->model->getInterfaceConcept()->getId()}");
            }

            $this->logger->debug("Getting accessible interfaces using INTERFACE {$rbacIfc->getId()}");
            
            $ifcAtoms = ResourceList::makeFromInterface($this->session->getId(), $rbacIfc->getId())->getResources();
        
        // Else query interfaces for every active role
        } else {
            foreach ($this->getActiveRoles() as $roleAtom) {
                // Query accessible interfaces
                $ifcAtoms = array_merge($ifcAtoms, $roleAtom->getTargetAtoms(ProtoContext::REL_IFC_ROLES, true));
            }
        }

        // Filter (un)defined interfaces
        $ifcAtoms = array_filter(
            $ifcAtoms,
            function (Atom $ifcAtom) {
                if ($this->model->interfaceExists($ifcAtom->getId())) {
                    return true;
                } else {
                    $this->logger->warning("Interface id '{$ifcAtom->getId()}' specified as accessible interface, but this interface is not defined. Run meta population installer to delete those interfaces");
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
     */
    public function getSession(): Session
    {
        if (is_null($this->session)) {
            throw new FatalException("Session not yet initialized");
        }
        return $this->session;
    }

    /**
     * Get Ampersand model for this application
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get settings object for this application
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
     * Open new transaction
     *
     * Note! Make sure that a open transaction is closed first
     */
    public function newTransaction(): Transaction
    {
        $transaction = new Transaction($this, Logger::getLogger('TRANSACTION'));
        $this->eventDispatcher()->dispatch(new TransactionEvent($transaction), TransactionEvent::STARTED);
        $this->transactions[] = $transaction;
        return $transaction;
    }
    
    /**
     * Return current open transaction or open new transactions
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
     * Use $installDefaultPop to specify whether or not to install the default population
     */
    public function reinstall(bool $installDefaultPop = true, bool $ignoreInvariantRules = false): self
    {
        $this->logger->info("Start application reinstall");

        // Increase timeout to at least 5 min
        if ($this->getSettings()->get('global.scriptTimeout') < 300) {
            set_time_limit(300);
            $this->logger->debug('PHP script timeout increased to 300 seconds');
        }

        // Clear notifications
        $this->userLogger->clearAll();

        $this->model->init($this);

        // Call reinstall method on every registered storage (e.g. for MysqlDB implementation this means (re)creating database structure)
        foreach ($this->storages as $storage) {
            $storage->reinstallStorage($this->model);
        }

        $this->registerCurrentModelVersion();

        $this->init();

        // Clear caches
        $this->conjunctCache->clear(); // external cache item pool
        foreach ($this->model->getAllConcepts() as $cpt) {
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
            throw new AmpersandException("Error while installing metapopulation and navigation menus: {$e->getMessage()}", previous: $e);
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
            throw new MetaModelException("Initial installation does not satisfy invariant rules. See log files");
        }

        // Evaluate all conjunct and save cache
        $this->logger->info("Initial evaluation of all conjuncts after application reinstallation");
        foreach ($this->model->getAllConjuncts() as $conj) {
            $conj->evaluate()->persistCacheItem();
        }

        $this->userLogger->notice("Application successfully reinstalled");
        $this->logger->info("End application reinstall");

        return $this;
    }

    public function registerCurrentModelVersion(): self
    {
        foreach ($this->storages as $storage) {
            $storage->addToModelVersionHistory($this->model);
        }
        return $this;
    }

    public function verifyChecksum(): bool
    {
        $check = true;
        foreach ($this->storages as $storage) {
            if ($storage->getInstalledModelHash() !== $this->model->checksum) {
                $this->logger->warning("Installed model hash registered in {$storage->getLabel()} does not match application model version. Reinstall or migrate application");
                $check = false;
            }
        }
        return $check;
    }

    /**
     * (De)activate session roles
     */
    public function setActiveRoles(array $roles): void
    {
        foreach ($roles as $role) {
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

        $lastTransactionIndex = array_key_last($this->transactions);
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
     */
    public function hasRole(?array $roles = null): bool
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
     */
    public function hasActiveRole(?array $roles = null): bool
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
     */
    public function isAccessibleIfc(Ifc $ifc): bool
    {
        return in_array($ifc, $this->accessibleInterfaces, true);
    }

    /**
     * Evaluate and signal violations for all rules that are maintained by the activated roles
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
     */
    public function getRelation(string $relationSignature, ?Concept $srcConcept = null, ?Concept $tgtConcept = null): Relation
    {
        return $this->model->getRelation($relationSignature, $srcConcept, $tgtConcept);
    }
}
