<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Log;

use Closure;
use Exception;
use Cascade\Cascade;
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
     *
     * @param string $channel
     * @return \Psr\Log\LoggerInterface
     */
    public static function getLogger(string $channel): LoggerInterface
    {
        if (Registry::hasLogger($channel)) {
            return Cascade::getLogger($channel);
        } else {
            throw new Exception("Log channel '{$channel}' not configured", 500);
        }
    }
}
