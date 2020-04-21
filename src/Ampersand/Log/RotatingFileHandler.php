<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Log;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class RotatingFileHandler extends \Monolog\Handler\RotatingFileHandler
{
    public function __construct($filename, $maxFiles = 0, $level = \Monolog\Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false)
    {
        $filename = dirname(__FILE__, 4) . DIRECTORY_SEPARATOR . $filename; // Adds project base path
        parent::__construct($filename, $maxFiles, $level, $bubble, $filePermission, $useLocking);
    }
}
