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
class Notifications extends AbstractLogger
{
    private static $errors = [];
    private static $invariants = [];
    private static $warnings = [];
    private static $signals = [];
    private static $infos = [];
    private static $successes = [];

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
                self::$infos[] = ['message' => $message];
                break;
            case LogLevel::NOTICE: // Notice
                self::$successes[] = ['message' => $message];
                break;
            case LogLevel::WARNING: // Warning
                $hash = hash('md5', $message);
                self::$warnings[$hash]['message'] = $message;
                self::$warnings[$hash]['count']++;
                break;
            case LogLevel::ERROR: // Error
            case LogLevel::CRITICAL: // Critical
            case LogLevel::ALERT: // Alert
            case LogLevel::EMERGENCY: // Emergency
                $errorHash = hash('md5', $message);
                self::$errors[$errorHash]['message'] = $message;
                self::$errors[$errorHash]['count']++;
                break;
            default:
                throw new Exception("Unsupported log level: {$level}", 500);
        }
    }

    public function getAll()
    {
        return [ 'errors' => array_values(self::$errors)
               , 'warnings' => array_values(self::$warnings)
               , 'infos' => array_values(self::$infos)
               , 'successes' => array_values(self::$successes)
               , 'invariants' => array_values(self::$invariants)
               , 'signals' => array_values(self::$signals)
               ];
    }

    /**
     * Clear all notification arrays
     *
     * @return void
     */
    public function clearAll()
    {
        self::$errors = [];
        self::$warnings = [];
        self::$infos = [];
        self::$successes = [];
        self::$invariants = [];
        self::$signals = [];
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
            
        self::$invariants[$hash]['ruleMessage'] = $violation->rule->getViolationMessage();
        self::$invariants[$hash]['tuples'][] = ['violationMessage' => ($violationMessage = $violation->getViolationMessage())];
        
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
        
        if (!isset(self::$signals[$ruleHash])) {
            self::$signals[$ruleHash]['message'] = $violation->rule->getViolationMessage();
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
        
        self::$signals[$ruleHash]['violations'][] = ['message' => $message
                                                    ,'ifcs' => $ifcs
                                                    ];
        
        Logger::getLogger('RULEENGINE')->debug("SIGNAL '{$message}' RULE: '{$violation->rule}'");
    }
}
