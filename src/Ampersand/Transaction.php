<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Exception;
use Ampersand\Misc\Hook;
use Ampersand\Core\Concept;
use Ampersand\Core\Relation;
use Ampersand\Plugs\StorageInterface;
use Ampersand\Rule\RuleEngine;
use Ampersand\Rule\ExecEngine;
use Ampersand\Rule\Rule;
use Ampersand\AmpersandApp;
use Psr\Log\LoggerInterface;
use Ampersand\Log\Logger;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Transaction
{
    /**
     * Points to the current open transaction
     *
     * @var \Ampersand\Transaction|null
     */
    private static $currentTransaction = null;
    
    /**
     * Transaction number (random int)
     *
     * @var int
     */
    private $id;
    
    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to Ampersand app for which this transaction is instantiated
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $app;
    
    /**
     * Contains all affected Concepts during a transaction
     *
     * @var \Ampersand\Core\Concept[]
     */
    private $affectedConcepts = [];
    
    /**
     * Contains all affected relations during a transaction
     *
     * @var \Ampersand\Core\Relation[]
     */
    private $affectedRelations = [];
    
    /**
     * Specifies if invariant rules hold. Null if no transaction has occurred (yet)
     *
     * @var bool|NULL
     */
    private $invariantRulesHold = null;
    
    /**
     * Specifies if the transaction is committed or rolled back
     *
     * @var bool
     */
    private $isCommitted = null;
    
    /**
     * List with storages that are affected in this transaction
     * Used to commit/rollback all storages when this transaction is closed
     *
     * @var \Ampersand\Plugs\StorageInterface[] $storages
     */
    private $storages = [];

    /**
     * List of exec engines
     *
     * @var \Ampersand\Rule\ExecEngine[]
     */
    protected $execEngines = [];
    
    /**
     * Constructor
     * Note! Don't use this constructor. Use AmpersandApp::newTransaction of AmpersandApp::getCurrentTransaction instead
     *
     * @param \Ampersand\AmpersandApp $app
     */
    public function __construct(AmpersandApp $app, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->app = $app;

        // Check to ensure only a single open transaction. AmpersandApp class is responsible for this.
        if (!is_null(self::$currentTransaction)) {
            throw new Exception("Something is wrong in the code. Only a single open transaction is allowed.", 500);
        } else {
            self::$currentTransaction = $this;
        }

        $this->id = rand();
        $this->logger->info("Opening transaction: {$this->id}");
        $this->initExecEngines();
    }

    /**
     * Function is called when object is treated as a string
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'Transaction ' . $this->id;
    }

    protected function initExecEngines()
    {
        $execEngineRoleNames = $this->app->getSettings()->get('execengine.execEngineRoleNames');
        foreach ((array) $execEngineRoleNames as $roleName) {
            try {
                $role = Role::getRoleByName($roleName);
                $this->execEngines[] = new ExecEngine($role, $this->app, Logger::getLogger('EXECENGINE'));
            } catch (Exception $e) {
                $this->logger->warning("ExecEngine role '{$roleName}' configured, but role is not used/defined in &-script");
            }
        }
    }

    /**
     * Run exec engines
     *
     * @param bool $checkAllRules specifies if all rules must be evaluated (true) or only the affected rules in this transaction (false)
     * @return \Ampersand\Transaction
     */
    public function runExecEngine(bool $checkAllRules = false): Transaction
    {
        $logger = Logger::getLogger('EXECENGINE');
        $logger->info("ExecEngine started");

        // Initial values
        $maxRunCount = $this->app->getSettings()->get('execengine.maxRunCount');
        $autoRerun = $this->app->getSettings()->get('execengine.autoRerun');
        $doRun = true;
        $runCounter = 0;

        // Rules to check
        $rulesToCheck = $checkAllRules ? Rule::getAllRules() : $this->getAffectedRules();

        // Do run exec engines while there is work to do
        do {
            $runCounter++;
            $logger->info("{+ Run #{{$runCounter}} (auto rerun: " . var_export($autoRerun, true) . ")");
            
            // Exec all exec engines
            $rulesFixed = [];
            foreach ($this->execEngines as $ee) {
                $logger->debug("Select exec engine '{{$ee->getId()}}'");
                $rulesFixed = array_merge($rulesFixed, $ee->checkFixRules($rulesToCheck));
            }

            // If no rules fixed (i.e. no violations) in this loop: stop exec engine
            if (empty($rulesFixed)) {
                $doRun = false;
            }

            // Prevent infinite loop in exec engine reruns
            if ($runCounter >= $maxRunCount && $doRun) {
                $logger->error("Maximum reruns exceeded. Rules fixed in last run:" . implode(', ', $rulesFixed) . ")");
                $this->app->userLog()->error("Maximum reruns exceeded for ExecEngine", ['Rules fixed in last run' => $rulesFixed]);
                $doRun = false;
            }
            $logger->info("+} Exec engine run finished");
            $rulesToCheck = $this->getAffectedRules(); // next run only affected rules need to be checked
        } while ($doRun && $autoRerun);

        $logger->info("ExecEngine finished");
        
        return $this;
    }

    /**
     * Run exec engine for affected rules in this transaction
     *
     * @param string $id
     * @return \Ampersand\Transaction
     */
    public function singleRunForExecEngine(string $id): Transaction
    {
        // Find/run specific exec engine
        foreach ($this->execEngines as $ee) {
            if ($ee->getId() == $id) {
                $ee->checkFixRules($this->getAffectedRules());
            }
        }

        return $this;
    }

    /**
     * Cancel (i.e. rollback) the transaction
     *
     * @return \Ampersand\Transaction
     */
    public function cancel(): Transaction
    {
        $this->logger->info("Request to cancel transaction: {$this->id}");

        if ($this->isClosed()) {
            throw new Exception("Cannot cancel transaction, because transaction is already closed", 500);
        }

        $this->rollback();

        self::$currentTransaction = null; // unset currentTransaction
        return $this;
    }

    /**
     * Alias for closing the transaction with the intention to rollback
     * Affected conjuncts are evaluated and invariant rule violations are reported
     *
     * @return Transaction
     */
    public function dryRun(): Transaction
    {
        return $this->close(true);
    }
    
    /**
     * Close transaction
     *
     * @param bool $dryRun
     * @param bool $ignoreInvariantViolations
     * @return \Ampersand\Transaction $this
     */
    public function close(bool $dryRun = false, bool $ignoreInvariantViolations = false): Transaction
    {
        $this->logger->info("Request to close transaction: {$this->id}");
        
        if ($this->isClosed()) {
            throw new Exception("Cannot close transaction, because transaction is already closed", 500);
        }
        
        Hook::callHooks('preCloseTransaction', get_defined_vars());

        // (Re)evaluate affected conjuncts
        foreach ($this->getAffectedConjuncts() as $conj) {
            $conj->evaluate(); // violations are persisted below, only when transaction is committed
        }

        // Check invariant rules
        $this->invariantRulesHold = $this->checkInvariantRules();
        
        // Decide action (commit or rollback)
        if ($dryRun) {
            $this->logger->info("Rollback transaction, because dry run was requested");
            $this->rollback();
        } else {
            if ($this->invariantRulesHold) {
                $this->logger->info("Commit transaction: {$this->id}");
                $this->commit();
            } elseif (!$this->invariantRulesHold && ($this->app->getSettings()->get('transactions.ignoreInvariantViolations') || $ignoreInvariantViolations)) {
                $this->logger->warning("Commit transaction {$this->id} with invariant violations");
                $this->commit();
            } else {
                $this->logger->info("Rollback transaction {$this->id}, because invariant rules do not hold");
                $this->rollback();
            }
        }
        
        Hook::callHooks('postCloseTransaction', get_defined_vars());
        
        self::$currentTransaction = null; // unset currentTransaction
        return $this;
    }

    /**
     * Commit transaction
     *
     * @return void
     */
    protected function commit()
    {
        // Cache conjuncts
        foreach ($this->getAffectedConjuncts() as $conj) {
            $conj->persistCacheItem();
        }

        // Commit transaction for each registered storage
        foreach ($this->storages as $storage) {
            $storage->commitTransaction($this);
        }

        $this->isCommitted = true;
    }

    /**
     * Rollback transaction
     *
     * @return void
     */
    protected function rollback()
    {
        // Rollback transaction for each registered storage
        foreach ($this->storages as $storage) {
            $storage->rollbackTransaction($this);
        }

        // Clear atom cache for affected concepts
        foreach ($this->affectedConcepts as $cpt) {
            $cpt->clearAtomCache();
        }

        $this->isCommitted = false;
    }
    
    /**
     * Add storage implementation to this transaction
     *
     * @param \Ampersand\Plugs\StorageInterface $storage
     * @return void
     */
    private function addAffectedStorage(StorageInterface $storage)
    {
        if (!in_array($storage, $this->storages)) {
            $this->logger->debug("Add storage: " . $storage->getLabel());
            $this->storages[] = $storage;
        }
    }
    
    public function getAffectedConcepts()
    {
        return $this->affectedConcepts;
    }
    
    public function getAffectedRelations()
    {
        return $this->affectedRelations;
    }
    
    /**
     * Mark a concept as affected within the open transaction
     * @param Concept $concept
     * @return void
     */
    public function addAffectedConcept(Concept $concept)
    {
        if (!in_array($concept, $this->affectedConcepts)) {
            $this->logger->debug("Mark concept '{$concept}' as affected concept");
            
            foreach ($concept->getPlugs() as $plug) {
                $this->addAffectedStorage($plug); // Register storage in this transaction
                $plug->startTransaction($this); // Start transaction for this storage
            }

            $this->affectedConcepts[] = $concept;
        }
    }
    
    /**
     * Mark a relation as affected within the open transaction
     * @param Relation $relation
     * @return void
     */
    public function addAffectedRelations(Relation $relation)
    {
        if (!in_array($relation, $this->affectedRelations)) {
            $this->logger->debug("Mark relation '{$relation}' as affected relation");

            foreach ($relation->getPlugs() as $plug) {
                $this->addAffectedStorage($plug); // Register storage in this transaction
                $plug->startTransaction($this); // Start transaction for this storage
            }

            $this->affectedRelations[] = $relation;
        }
    }

    /**
     * Return list of affected conjuncts in this transaction
     *
     * @return \Ampersand\Rule\Conjunct[]
     */
    public function getAffectedConjuncts()
    {
        $affectedConjuncts = [];
        
        // Get conjuncts for affected concepts and relations
        foreach ($this->affectedConcepts as $concept) {
            $affectedConjuncts = array_merge($affectedConjuncts, $concept->getRelatedConjuncts());
        }
        foreach ($this->affectedRelations as $relation) {
            $affectedConjuncts = array_merge($affectedConjuncts, $relation->getRelatedConjuncts());
        }
        
        // Remove duplicates and return conjuncts
        return array_unique($affectedConjuncts);
    }

    /**
     * Get list of rules that are affected in this transaction
     *
     * @return \Ampersand\Rule\Rule[]
     */
    public function getAffectedRules(): array
    {
        $affectedRuleNames = [];
        foreach ($this->getAffectedConjuncts() as $conjunct) {
            $affectedRuleNames = array_merge($affectedRuleNames, $conjunct->getRuleNames());
        }
        $affectedRuleNames = array_unique($affectedRuleNames);

        return array_map(function (string $ruleName): Rule {
            return Rule::getRule($ruleName);
        }, $affectedRuleNames);
    }

    /**
     * Returns if invariant rules hold and notifies user of violations (if any)
     * Note! Only checks affected invariant rules
     *
     * @return bool
     */
    protected function checkInvariantRules(): bool
    {
        $this->logger->info("Checking invariant rules");
        
        $affectedInvRules = array_filter($this->getAffectedRules(), function (Rule $rule) {
            return $rule->isInvariantRule();
        });

        $rulesHold = true;
        foreach (RuleEngine::getViolations($affectedInvRules) as $violation) {
            $rulesHold = false; // set to false if there is one or more violation
            $this->app->userLog()->invariant($violation); // notify user of broken invariant rules
        }

        return $rulesHold;
    }
    
    public function invariantRulesHold()
    {
        return $this->invariantRulesHold;
    }
    
    public function isCommitted()
    {
        return $this->isCommitted === true;
    }
    
    public function isRolledBack()
    {
        return $this->isCommitted === false;
    }
    
    public function isOpen()
    {
        return $this->isCommitted === null;
    }
    
    public function isClosed()
    {
        return $this->isCommitted !== null;
    }
}
