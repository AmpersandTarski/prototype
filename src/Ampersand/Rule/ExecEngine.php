<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

use Exception;
use Ampersand\Role;
use Ampersand\Log\Logger;
use Ampersand\Rule\Violation;
use Ampersand\Core\Atom;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Closure;
use Ampersand\AmpersandApp;
use Ampersand\Transaction;

class ExecEngine extends RuleEngine
{
    use LoggerTrait;

    /**
     * List of closures (functions) that can used by the ExecEngine
     *
     * @var \Closure[]
     */
    protected static array $closures = [];

    /**
     * Logger
     */
    protected LoggerInterface $logger;

    /**
     * Identifier (role id) of this exec engine
     */
    protected string $id;

    /**
     * Rules this ExecEngine maintains
     *
     * @var \Ampersand\Rule\Rule[]
     */
    protected array $maintainsRules;

    /**
     * Reference to the Transaction for which this ExecEngine is instantiated
     */
    protected Transaction $transaction;

    /**
     * Reference to Ampersand app for which this ExecEngine is instantiated
     */
    protected AmpersandApp $ampersandApp;

    /**
     * Number of runs this exec engine is called (i.e. the checkFixRules() method)
     */
    protected int $runCount = 0;
    
    /**
     * Specifies latest atom created by a Newstruct/InsAtom function call
     *
     * Can be (re)used within the scope of one violation statement
     */
    protected ?Atom $newAtom = null;

    /**
     * Specifies is this exec engine is terminated (i.e. it won't check-fix rules anymore)
     */
    protected bool $isTerminated = false;

    /**
     * Constructor
     */
    public function __construct(Role $role, Transaction $transaction, AmpersandApp $app, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->id = $role->getLabel();
        $this->maintainsRules = $role->maintains();
        $this->transaction = $transaction;
        $this->ampersandApp = $app;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getApp(): AmpersandApp
    {
        return $this->ampersandApp;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, $context);
    }

    public function userLog(): LoggerInterface
    {
        return $this->ampersandApp->userLog();
    }

    /**
     * Get created atom by other ExecEngine function
     *
     * Only is available within the same violation: $newAtom is set to null at the start of each fixViolation run
     */
    public function getCreatedAtom(): Atom
    {
        if (is_null($this->newAtom)) {
            throw new Exception("No newly created atom (_NEW) available. To fix: first execute function InsAtom.", 500);
        }
        return $this->newAtom;
    }

    /**
     * Set created atom so it can be (re)used within the scope of one violation statement
     */
    public function setCreatedAtom(Atom $atom): Atom
    {
        return $this->newAtom = $atom;
    }

    public function getRunCount(): int
    {
        return $this->runCount;
    }
    
    /**
     * Perform single run for this exec engine
     *
     * @param \Ampersand\Rule\Rule[] $affectedRules
     * @return \Ampersand\Rule\Rule[] $rulesFixed
     */
    public function checkFixRules(array $affectedRules): array
    {
        // Quit immediately when ExecEngine is terminated
        if ($this->isTerminated) {
            $this->debug("Skipping run for '{$this->id}', because it is terminated");
            return [];
        }

        $this->runCount++;

        // Filter rules that are maintained by this exec engine
        $rulesToCheck = array_filter($this->maintainsRules, function (Rule $rule) use ($affectedRules) {
            return in_array($rule, $affectedRules);
        });

        $rulesFixed = [];
        foreach ($rulesToCheck as $rule) {
            $violations = $rule->checkRule(true); // param true to force (re)evaluation of conjuncts
            
            if (empty($violations)) {
                continue; // continue to next rule when no violation
            }
            
            // Fix violations
            $total = count($violations);
            $this->info("ExecEngine fixing {$total} violations for rule '{$rule}'");
            foreach ($violations as $key => $violation) {
                $num = $key + 1;
                $this->info("Fixing violation {$num}/{$total}: ({$violation})");
                $this->fixViolation($violation);
                
                // Abort loop when exec engine is terminated
                if ($this->isTerminated) {
                    $this->debug("Aborting run for '{$this->id}', because it is terminated");
                    break 2;
                }
            }
            $rulesFixed[] = $rule;
            $this->notice("ExecEngine fixed {$total} violations for rule '{$rule}'");
        }

        return $rulesFixed;
    }
    
    /**
     * Fix violation
     */
    protected function fixViolation(Violation $violation): void
    {
        // Reset reference to newly created atom (e.g. by NewStruct/InsAtom function)
        // See function getCreatedAtom() above
        $this->newAtom = null;

        // Determine actions/functions to be taken
        $actions = explode('{EX}', $violation->getExecEngineViolationMessage());
        
        // Execute actions/functions to fix this violation
        foreach ($actions as $action) {
            if (empty($action)) {
                continue; // skips to the next iteration if $action is empty. This is the case when violation text starts with delimiter {EX}
            }
            
            // Determine delimiter
            if (substr($action, 0, 2) === '_;') {
                $delimiter = '_;';
                $action = substr($action, 2);
            } else {
                $delimiter = ';';
            }
            
            // Split off variables
            $params = explode($delimiter, $action);
            //$params = array_map('trim', $params); // trim all params // commented out, because atoms can have spaces in them
            
            // Evaluate php statement if provided as param
            $params = array_map(function ($param) {
                // If php function is provided, evaluate this.
                // Limited security issue, because '{php}' can only be specified in &-script. '{php}' in user input is filtered out when getting violation message
                // Only 1 php statement can be executed, due to semicolon issue: PHP statements must be properly terminated using a semicolon, but the semicolon is already used to seperate the parameters
                // e.g. {php}date(DATE_ISO8601) returns the current datetime in ISO 8601 date format
                if (substr($param, 0, 5) === '{php}') {
                    $code = 'return('.substr($param, 5).');';
                    $param = eval($code);
                }
                return $param;
            }, $params);
            
            $functionName = trim(array_shift($params)); // first parameter is function name
            $closure = self::getFunction($functionName);
            try {
                $this->info("{$functionName}(" . implode(',', $params) . ")");
                $closure->call($this, ...$params);
            // Catch exceptions from ExecEngine functions and log to user
            } catch (Exception $e) {
                $this->ampersandApp->userLog()->error("{$functionName}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Terminate this exec engine
     *
     * It won't check-fix rules anymore (within the transaction the exec engine is instantiated)
     */
    public function terminate(): void
    {
        $this->isTerminated = true;
    }

    /**
     * Trigger a run for a specific service
     *
     * This method allows an ExecEngine function to trigger a service run
     */
    public function triggerService(string $serviceId): void
    {
        $this->transaction->requestServiceRun($serviceId);
    }

    /**********************************************************************************************
     * STATIC METHODS
     *********************************************************************************************/

    /**
     * Get registered ExecEngine function
     */
    public static function getFunction(string $functionName): Closure
    {
        if (array_key_exists($functionName, self::$closures)) {
            return self::$closures[$functionName];
        } else {
            throw new Exception("Function '{$functionName}' does not exist. Register ExecEngine function.", 500);
        }
    }

    /**
     * Register functions that can be used by the ExecEngine to fix violations
     */
    public static function registerFunction(string $name, Closure $closure): void
    {
        if (empty($name)) {
            throw new Exception("ExecEngine function must be given a name. Empty string/0/null provided", 500);
        }
        if (array_key_exists($name, self::$closures)) {
            throw new Exception("ExecEngine function '{$name}' already exists", 500);
        }
        self::$closures[$name] = $closure;
        Logger::getLogger('EXECENGINE')->debug("ExecEngine function '{$name}' registered");
    }
}
