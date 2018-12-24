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

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class UserLogger extends AbstractLogger
{
    /**
     * Logger instance
     * All notifications/logs for the user are also logged to this logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected $errors = [];
    protected $invariants = [];
    protected $warnings = [];
    protected $signals = [];
    protected $infos = [];
    protected $successes = [];

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
     *
     * @param \Ampersand\Rule\Violation $violation
     * @return void
     */
    public function invariant(Violation $violation)
    {
        $hash = hash('md5', $violation->rule->id);
            
        $this->invariants[$hash]['ruleMessage'] = $violation->rule->getViolationMessage();
        $this->invariants[$hash]['tuples'][] = ['violationMessage' => ($violationMessage = $violation->getViolationMessage())];
        
        $this->logger->info("INVARIANT '{$violationMessage}' RULE: '{$violation->rule}'");
    }
    
    /**
     * Notify user of signal rule violation
     *
     * @param \Ampersand\Rule\Violation $violation
     * @return void
     */
    public function signal(Violation $violation)
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove reference to global

        $ruleHash = hash('md5', $violation->rule->id);
        
        if (!isset($this->signals[$ruleHash])) {
            $this->signals[$ruleHash]['message'] = $violation->rule->getViolationMessage();
        }
        
        // Add links for src atom
        $ifcs = array_map(function (Ifc $ifc) use ($violation) {
            /** @var \Ampersand\Interfacing\InterfaceObjectInterface $ifcobj */
            $ifcobj = $ifc->getIfcObject();
            return ['id' => $ifcobj->getIfcId(),
                    'label' => $ifcobj->getIfcLabel(),
                    'link' => "#/{$ifcobj->getIfcId()}/{$violation->src->id}"
                    ];
        }, $ampersandApp->getInterfacesToReadConcept($violation->src->concept));

        // Add links for tgt atom (if not the same as src atom)
        if ($violation->src->concept !== $violation->tgt->concept || $violation->src->id !== $violation->tgt->id) {
            array_merge($ifcs, array_map(
                function (Ifc $ifc) use ($violation) {
                    /** @var \Ampersand\Interfacing\InterfaceObjectInterface $ifcobj */
                    $ifcobj = $ifc->getIfcObject();
                    return [ 'id' => $ifcobj->getIfcId()
                           , 'label' => $ifcobj->getIfcLabel()
                           , 'link' => "#/{$ifcobj->getIfcId()}/{$violation->tgt->id}"
                           ];
                },
                $ampersandApp->getInterfacesToReadConcept($violation->tgt->concept)
            ));
        }

        $message = $violation->getViolationMessage();
        
        $this->signals[$ruleHash]['violations'][] = ['message' => $message
                                                    ,'ifcs' => array_values($ifcs)
                                                    ];
        
        $this->logger->debug("SIGNAL '{$message}' RULE: '{$violation->rule}'");
    }
}
