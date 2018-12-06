<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Ampersand\IO\AbstractReader;
use Exception;

class JSONReader extends AbstractReader
{
    protected static $messages = [
        JSON_ERROR_NONE => 'No error has occurred',
        JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
        JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
        JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX => 'Syntax error',
        JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    ];

    public function getContent()
    {
        $content = json_decode(stream_get_contents($this->stream, -1, 0));
        
        if (is_null($content)) {
            throw new Exception(static::$messages[json_last_error()] . " in file '{$this->filename}'", 500);
        } else {
            return $content;
        }
    }
}
