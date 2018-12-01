<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Ampersand\IO\AbstractReader;

class JSONReader extends AbstractReader
{

    public function getContent()
    {
        $content = json_decode(stream_get_contents($this->stream, -1, 0));
        
        if (is_null($content)) {
            throw new Exception(static::$_messages[json_last_error()] . " in file '{$this->filename}'", 500);
        } else {
            return $content;
        }
    }
}
