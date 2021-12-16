<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Log;

use Exception;
use Ampersand\Rule\Violation;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Ampersand\Interfacing\Ifc;
use Ampersand\AmpersandApp;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class UserLogger extends AbstractLogger
{
    /**
     * Logger instance
     *
     * All notifications/logs for the user are also logged to this logger
     */
    protected LoggerInterface $logger;

    /**
     * Reference to Ampersand app for which this logger is defined
     */
    protected AmpersandApp $app;

    protected $errors = [];
    protected $invariants = [];
    protected $warnings = [];
    protected $signals = [];
    protected $infos = [];
    protected $successes = [];

    /**
     * Constructor
     */
    public function __construct(AmpersandApp $app, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->app = $app;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    public function log($level, $message, array $context = array()): void
    {
        // Also log to non-user log
        $this->logger->log($level, $message, $context);

        // Add to notification list for UI
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
                if (!empty($context)) {
                    $rows = array_map(function ($item) {
                        if (is_array($item)) {
                            return array_map('strval', $item);
                        } else {
                            return strval($item);
                        }
                    }, $context);
                    $this->errors[$errorHash]['details'] = "<pre>" . print_r($rows, true) . "</pre>";
                }
                break;
            default:
                throw new Exception("Unsupported log level: {$level}", 500);
        }
    }

    public function getAll(): array
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
     */
    public function clearAll(): void
    {
        $this->logger->debug("Clear user notification lists");
        
        $this->errors = [];
        $this->warnings = [];
        $this->infos = [];
        $this->successes = [];
        $this->invariants = [];
        $this->signals = [];
    }

    /**
     * Notify user of invariant rule violation
     */
    public function invariant(Violation $violation): void
    {
        $rule = $violation->getRule();
        $hash = hash('md5', $rule->getId());
            
        $this->invariants[$hash]['ruleMessage'] = $rule->getViolationMessage();
        $this->invariants[$hash]['tuples'][] = ['violationMessage' => ($violationMessage = $violation->getViolationMessage())];
        
        $this->logger->info("INVARIANT '{$violationMessage}' RULE: '{$rule}'");
    }
    
    /**
     * Notify user of signal rule violation
     */
    public function signal(Violation $violation): void
    {
        $rule = $violation->getRule();
        $ruleHash = hash('md5', $rule->getId());
        
        if (!isset($this->signals[$ruleHash])) {
            $this->signals[$ruleHash]['message'] = $rule->getViolationMessage();
        }
        
        // Add links for src atom
        $ifcs = array_map(function (Ifc $ifc) use ($violation) {
            $ifcobj = $ifc->getIfcObject();
            return ['id' => $ifcobj->getIfcId(),
                    'label' => $ifcobj->getIfcLabel(),
                    'link' => "#/{$ifcobj->getIfcId()}/{$violation->getSrc()->getId()}"
                    ];
        }, $this->app->getInterfacesToReadConcept($violation->getSrc()->concept));

        // Add links for tgt atom (if not the same as src atom)
        if ($violation->getSrc()->concept !== $violation->getTgt()->concept || $violation->getSrc()->getId() !== $violation->getTgt()->getId()) {
            array_merge($ifcs, array_map(
                function (Ifc $ifc) use ($violation) {
                    $ifcobj = $ifc->getIfcObject();
                    return [ 'id' => $ifcobj->getIfcId()
                           , 'label' => $ifcobj->getIfcLabel()
                           , 'link' => "#/{$ifcobj->getIfcId()}/{$violation->getTgt()->getId()}"
                           ];
                },
                $this->app->getInterfacesToReadConcept($violation->getTgt()->concept)
            ));
        }

        $message = $violation->getViolationMessage();
        
        $this->signals[$ruleHash]['violations'][] = ['message' => $message
                                                    ,'ifcs' => array_values($ifcs)
                                                    ];
        
        $this->logger->debug("SIGNAL '{$message}' RULE: '{$rule}'");
    }
}
