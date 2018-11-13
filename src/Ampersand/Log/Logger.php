<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Log;

use Psr\Log\LoggerInterface;
use Closure;
use Psr\Log\NullLogger;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Logger
{
    /**
     * Contains all instantiated loggers
     * @var \Ampersand\Log\Logger[]
     */
    private static $loggers = [];

    /**
     * Factory function to create new loggers
     * @var \Closure
     */
    protected static $factoryFunction = null;

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
        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        } else {
            if (is_null(self::$factoryFunction)) {
                return new NullLogger();
            }

            return self::$loggers[$channel] = call_user_func(self::$factoryFunction, $channel);
        }
    }
    
    /**
     * Get logger instance to communicate to user interface
     *
     * @return \Psr\Log\LoggerInterface
     */
    public static function getUserLogger(): LoggerInterface
    {
        return Logger::getLogger('USERLOG');
    }

    /**
     * Set logger for a certain channel
     *
     * @param string $channel
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public static function setLogger(string $channel, LoggerInterface $logger)
    {
        self::$loggers[$channel] = $logger;
    }
    
    /**
     * Register a closure that is called upon initialization of a logger
     *
     * @param \Closure $closure
     * @return void
     */
    public static function setFactoryFunction(Closure $closure)
    {
        self::$factoryFunction = $closure;
    }
}
