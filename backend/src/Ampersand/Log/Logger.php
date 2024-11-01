<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Log;

use Ampersand\Exception\InvalidConfigurationException;
use Monolog\Registry;
use Psr\Log\LoggerInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Logger
{
    /******************
     * STATIC METHODS
     ******************/

    /**
     * Get logger instance with specified channel name
     */
    public static function getLogger(string $channel): LoggerInterface
    {
        if (Registry::hasLogger($channel)) {
            return Registry::getInstance($channel);
        } else {
            throw new InvalidConfigurationException("Log channel '{$channel}' not configured");
        }
    }
}
