<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Log;

use Exception;
use Ampersand\Log\Logger;
use Ampersand\Rule\Violation;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class UserLogger extends AbstractLogger
{
    protected $errors = [];
    protected $invariants = [];
    protected $warnings = [];
    protected $signals = [];
    protected $infos = [];
    protected $successes = [];

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        switch ($level) {
            case LogLevel::DEBUG:
                break;
            case LogLevel::INFO: // Info
                $this->infos[] = ['message' => $message];
                break;
            case LogLevel::NOTICE: // Notice
                $this->successes[] = ['message' => $message];
                break;
            case LogLevel::WARNING: // Warning
                $hash = hash('md5', $message);
                $this->warnings[$hash]['message'] = $message;
                $this->warnings[$hash]['count']++;
                break;
            case LogLevel::ERROR: // Error
            case LogLevel::CRITICAL: // Critical
            case LogLevel::ALERT: // Alert
            case LogLevel::EMERGENCY: // Emergency
                $errorHash = hash('md5', $message);
                $this->errors[$errorHash]['message'] = $message;
                $this->errors[$errorHash]['count']++;
                break;
            default:
                throw new Exception("Unsupported log level: {$level}", 500);
        }
    }

    public function getAll()
    {
        return [ 'errors' => array_values($this->errors)
               , 'warnings' => array_values($this->warnings)
               , 'infos' => array_values($this->infos)
               , 'successes' => array_values($this->successes)
               , 'invariants' => array_values($this->invariants)
               , 'signals' => array_values($this->signals)
               ];
    }

    /**
     * Clear all notification arrays
     *
     * @return void
     */
    public function clearAll()
    {
        $this->errors = [];
        $this->warnings = [];
        $this->infos = [];
        $this->successes = [];
        $this->invariants = [];
        $this->signals = [];
    }

    /**
     * Notify user of invariant rule violation
     *
     * @param \Ampersand\Rule\Violation $violation
     * @return void
     */
    public function invariant(Violation $violation)
    {
        $hash = hash('md5', $violation->rule->id);
            
        $this->invariants[$hash]['ruleMessage'] = $violation->rule->getViolationMessage();
        $this->invariants[$hash]['tuples'][] = ['violationMessage' => ($violationMessage = $violation->getViolationMessage())];
        
        Logger::getLogger('RULEENGINE')->info("INVARIANT '{$violationMessage}' RULE: '{$violation->rule}'");
    }
    
    /**
     * Notify user of signal rule violation
     *
     * @param \Ampersand\Rule\Violation $violation
     * @return void
     */
    public function signal(Violation $violation)
    {
        $ruleHash = hash('md5', $violation->rule->id);
        
        if (!isset($this->signals[$ruleHash])) {
            $this->signals[$ruleHash]['message'] = $violation->rule->getViolationMessage();
        }
        
        $ifcs = [];
        foreach ($violation->getInterfaces('src') as $ifc) {
            $ifcs[] = ['id' => $ifc->id, 'label' => $ifc->label, 'link' => "#/{$ifc->id}/{$violation->src->id}"];
        }
        if ($violation->src->concept != $violation->tgt->concept || $violation->src->id != $violation->tgt->id) {
            foreach ($violation->getInterfaces('tgt') as $ifc) {
                $ifcs[] = ['id' => $ifc->id, 'label' => $ifc->label, 'link' => "#/{$ifc->id}/{$violation->tgt->id}"];
            }
        }
        $message = $violation->getViolationMessage();
        
        $this->signals[$ruleHash]['violations'][] = ['message' => $message
                                                    ,'ifcs' => $ifcs
                                                    ];
        
        Logger::getLogger('RULEENGINE')->debug("SIGNAL '{$message}' RULE: '{$violation->rule}'");
    }
}
