<?php

namespace Ampersand;

use Ampersand\Misc\Settings;
use Ampersand\IO\Importer;
use Ampersand\Model;
use Ampersand\Transaction;
use Ampersand\Plugs\StorageInterface;
use Ampersand\Plugs\ConceptPlugInterface;
use Ampersand\Plugs\RelationPlugInterface;
use Ampersand\Rule\Conjunct;
use Ampersand\Session;
use Ampersand\Core\Atom;
use Exception;
use Ampersand\Interfacing\InterfaceObject;
use Ampersand\Core\Concept;
use Ampersand\Role;
use Ampersand\Rule\RuleEngine;
use Ampersand\Log\Notifications;
use Psr\Log\LoggerInterface;
use Ampersand\Log\Logger;
use Ampersand\Core\Relation;
use Ampersand\Interfacing\View;
use Ampersand\Rule\Rule;
use Closure;
use Ampersand\Rule\ExecEngine;
use Psr\Cache\CacheItemPoolInterface;
use Ampersand\IO\JSONReader;

class AmpersandApp
{
    /**
     * Specifies the required version of the localsettings file that
     * @const float
     */
    const REQ_LOCALSETTINGS_VERSION = 2.0;

    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

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
     * @var \Ampersand\Plugs\StorageInterface
     */
    protected $defaultStorage = null;

    /**
     * Cache implementation for conjunct violation cache
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    protected $conjunctCache = null;

    /**
     * List of custom plugs for concepts
     * @var ['conceptLabel' => \Ampersand\Plugs\ConceptPlugInterface[]]
     */
    protected $customConceptPlugs = [];

    /**
     * List of custom plugs for relations
     * @var ['relationSignature' => \Ampersand\Plugs\RelationPlugInterface[]]
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
     * @var Session
     */
    protected $session = null;

    /**
     * List of accessible interfaces for the user of this Ampersand application
     *
     * @var \Ampersand\Interfacing\InterfaceObject[] $accessibleInterfaces
     */
    protected $accessibleInterfaces = [];
    
    /**
     * List with rules that are maintained by the activated roles in this Ampersand application
     *
     * @var \Ampersand\Rule\Rule[] $rulesToMaintain
     */
    protected $rulesToMaintain = []; // rules that are maintained by active roles
    
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
        $this->model = $model;
        $this->settings = $settings;
        $this->settings->loadSettingsFile($model->getFilePath('settings'));

        $this->name = $this->settings->get('contextName');
    }

    public function getName()
    {
        return $this->name;
    }

    public function init()
    {
        try {
            $this->logger->info('Initialize Ampersand application');

            // Check checksum
            if (!$this->model->verifyChecksum() && !$this->settings->get('global.productionEnv')) {
                Logger::getUserLogger()->warning("Generated model is changed. You SHOULD reinstall your application");
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

            // Instantiate object definitions from generated files
            $genericsFolder = $this->model->getFolder() . '/';
            Conjunct::setAllConjuncts($genericsFolder . 'conjuncts.json', Logger::getLogger('RULEENGINE'), $this->defaultStorage, $this->conjunctCache);
            View::setAllViews($genericsFolder . 'views.json', $this->defaultStorage);
            Concept::setAllConcepts($genericsFolder . 'concepts.json', Logger::getLogger('CORE'));
            Relation::setAllRelations($genericsFolder . 'relations.json', Logger::getLogger('CORE'));
            InterfaceObject::setAllInterfaces($genericsFolder . 'interfaces.json', $this->defaultStorage);
            Rule::setAllRules($genericsFolder . 'rules.json', $this->defaultStorage, Logger::getLogger('RULEENGINE'));
            Role::setAllRoles($genericsFolder . 'roles.json');

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
            foreach (Relation::getAllRelations() as $rel) {
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
    public function registerInitClosure(Closure $closure)
    {
        $this->initClosures[] = $closure;
    }
    
    public function registerStorage(StorageInterface $storage)
    {
        if (!in_array($storage, $this->storages)) {
            $this->logger->debug("Add storage: " . $storage->getLabel());
            $this->storages[] = $storage;
        }
    }

    public function registerCustomConceptPlug(string $conceptLabel, ConceptPlugInterface $plug)
    {
        $this->customConceptPlugs[$conceptLabel][] = $plug;
        $this->registerStorage($plug);
    }

    public function registerCustomRelationPlug(string $relSignature, RelationPlugInterface $plug)
    {
        $this->customRelationPlugs[$relSignature][] = $plug;
        $this->registerStorage($plug);
    }

    public function setDefaultStorage(StorageInterface $storage)
    {
        $this->defaultStorage = $storage;
        $this->registerStorage($storage);
    }

    public function setConjunctCache(CacheItemPoolInterface $cache)
    {
        $this->conjunctCache = $cache;
    }

    public function setSession()
    {
        $this->session = new Session($this->logger, $this->settings);

        // Run exec engine and close transaction
        Transaction::getCurrentTransaction()->runExecEngine()->close();

        // Set accessible interfaces and rules to maintain
        $this->setInterfacesAndRules();
    }

    protected function setInterfacesAndRules()
    {
        // Add public interfaces
        $this->accessibleInterfaces = InterfaceObject::getPublicInterfaces();

        // Add interfaces and rules for all active session roles
        foreach ($this->getActiveRoles() as $roleAtom) {
            try {
                $role = Role::getRoleByName($roleAtom->id);
                $this->accessibleInterfaces = array_merge($this->accessibleInterfaces, $role->interfaces());
                $this->rulesToMaintain = array_merge($this->rulesToMaintain, $role->maintains());
            } catch (Exception $e) {
                $this->logger->debug("Actived role '{$roleAtom}', but role is not used/defined in &-script.");
            }
        }

        // Remove duplicates
        $this->accessibleInterfaces = array_unique($this->accessibleInterfaces);
        $this->rulesToMaintain = array_unique($this->rulesToMaintain);
    }

    /**
     * Get the session object for this instance of the ampersand application
     *
     * @return Session
     */
    public function getSession()
    {
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
     * @return \Ampersand\Interfacing\InterfaceObject[]
     */
    public function getAccessibleInterfaces()
    {
        return $this->accessibleInterfaces;
    }

    /**
     * Get the rules that are maintained by the active roles of this Ampersand application
     *
     * @return \Ampersand\Rule\Rule[]
     */
    public function getRulesToMaintain()
    {
        return $this->rulesToMaintain;
    }

    /**
     * Login user and commit transaction
     *
     * @return void
     */
    public function login(Atom $account)
    {
        // Renew session. See topic 'Renew the Session ID After Any Privilege Level Change' in OWASP session management cheat sheet
        $this->session->reset();

        // Set sessionAccount
        $this->session->setSessionAccount($account);

        $transaction = Transaction::getCurrentTransaction();

        // Run ExecEngine to populate session related relations (e.g. sessionAllowedRoles)
        $transaction->runExecEngine();

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
    public function logout()
    {
        // Renew session. See OWASP session management cheat sheet
        $this->session->reset();

        // Run exec engine and close transaction
        Transaction::getCurrentTransaction()->runExecEngine()->close();

        $this->setInterfacesAndRules();
    }

    /**
     * Function to reinstall the application. This includes database structure and load default population
     *
     * @param bool $installDefaultPop specifies whether or not to install the default population
     * @param bool $ignoreInvariantRules
     * @return \Ampersand\Transaction in which application is reinstalled
     */
    public function reinstall(bool $installDefaultPop = true, bool $ignoreInvariantRules = false): Transaction
    {
        $this->logger->info("Start application reinstall");

        // Clear notifications
        Notifications::clearAll();

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

        // Default population
        if ($installDefaultPop) {
            $this->logger->info("Install default population");

            $reader = (new JSONReader())->loadFile($this->model->getFilePath('populations'));
            $importer = new Importer($reader, Logger::getLogger('IO'));
            $importer->importPopulation();
        } else {
            $this->logger->info("Skip default population");
        }

        // Close transaction
        $transaction = Transaction::getCurrentTransaction()->runExecEngine()->close(false, $ignoreInvariantRules);
        if ($transaction->isRolledBack()) {
            throw new Exception("Initial installation does not satisfy invariant rules. See log files", 500);
        } else {
            Logger::getUserLogger()->notice("Application successfully reinstalled");
        }

        // Evaluate all conjunct and save cache
        $this->logger->info("Initial evaluation of all conjuncts after application reinstallation");
        foreach (Conjunct::getAllConjuncts() as $conj) {
            /** @var \Ampersand\Rule\Conjunct $conj */
            $conj->evaluate()->persistCacheItem();
        }

        $this->logger->info("End application reinstall");

        return $transaction;
    }

    /**
     * (De)activate session roles
     *
     * @param array $roles
     * @return void
     */
    public function setActiveRoles(array $roles)
    {
        foreach ($roles as $role) {
            // Set sessionActiveRoles[SESSION*Role]
            $this->session->toggleActiveRole(Concept::makeRoleAtom($role->label), $role->active);
        }
        
        // Commit transaction (exec-engine kicks also in)
        Transaction::getCurrentTransaction()->runExecEngine()->close();

        $this->setInterfacesAndRules();
    }

    /**
     * Get allowed roles
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getAllowedRoles()
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
        return $this->session->getSessionActiveRoles();
    }

    /**
     * Get session roles with their id, label and state (active or not)
     *
     * @return array
     */
    public function getSessionRoles(): array
    {
        $activeRoleIds = array_column($this->getActiveRoles(), 'id');
        
        return array_map(function (Atom $roleAtom) use ($activeRoleIds) {
            return (object) ['id' => $roleAtom->id
                            ,'label' => $roleAtom->getLabel()
                            ,'active' => in_array($roleAtom->id, $activeRoleIds)
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
            return in_array($role->id, $roles) || $carry;
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
            return in_array($role->id, $roles) || $carry;
        }, false);
    }

    /**
     * Get interfaces that are accessible in the current session to 'Read' a certain concept
     * @param \Ampersand\Core\Concept[] $concepts
     * @return \Ampersand\Interfacing\InterfaceObject[]
     */
    public function getInterfacesToReadConcepts($concepts)
    {
        return array_values(
            array_filter($this->accessibleInterfaces, function ($ifc) use ($concepts) {
                foreach ($concepts as $cpt) {
                    if ($ifc->srcConcept->hasSpecialization($cpt, true)
                        && $ifc->crudR()
                        && (!$ifc->crudC() or ($ifc->crudU() or $ifc->crudD()))
                        ) {
                        return true;
                    }
                }
                return false;
            })
        );
    }

    /**
     * Determine if provided concept is editable concept in one of the accessible interfaces in the current session
     * @param \Ampersand\Core\Concept $concept
     * @return boolean
     */
    public function isEditableConcept(Concept $concept)
    {
        return array_reduce($this->accessibleInterfaces, function ($carry, $ifc) use ($concept) {
            return ($carry || in_array($concept, $ifc->getEditableConcepts()));
        }, false);
    }
    
    /**
     * Determine if provided interface is accessible in the current session
     * @param \Ampersand\Interfacing\InterfaceObject $ifc
     * @return boolean
     */
    public function isAccessibleIfc(InterfaceObject $ifc)
    {
        return in_array($ifc, $this->accessibleInterfaces, true);
    }

    /**
     * Evaluate and signal violations for all rules that are maintained by the activated roles
     *
     * @return void
     */
    public function checkProcessRules()
    {
        $this->logger->debug("Checking process rules for active roles: " . implode(', ', array_column($this->getActiveRoles(), 'id')));
        
        // Check rules and signal notifications for all violations
        foreach (RuleEngine::getViolationsFromCache($this->getRulesToMaintain()) as $violation) {
            Notifications::addSignal($violation);
        }
    }
}
