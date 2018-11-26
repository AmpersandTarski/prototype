<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Exception;

abstract class AbstractReader
{

    /**
     * The stream used to input
     *
     * @var resource
     */
    protected $stream = null;

    /**
     * Tailing name component of filepath
     *
     * @var string
     */
    protected $filename;

    /**
     * Constructor
     *
     * @param array $options Configuration options
     */
    public function __construct($options = [])
    {
    }

    public function getContent()
    {
        return stream_get_contents($this->stream, -1, 0);
    }

    /**
     * Undocumented function
     *
     * @param string $filePath
     * @return \Ampersand\IO\AbstractReader $this
     */
    public function loadFile($filePath): AbstractReader
    {
        // Check if file exists
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("Could not open {$filePath}. File does not exist", 500);
        }

        $this->filename = pathinfo($filePath, PATHINFO_BASENAME);

        // Open file
        $this->stream = fopen($filePath, 'r');
        if ($this->stream === false) {
            throw new Exception("Could not open {$filePath}", 500);
        }

        return $this;
    }
}
