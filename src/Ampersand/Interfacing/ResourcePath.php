<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ResourcePath
{
    public static function makePathList(string $path = null): array
    {
        if (is_null($path)) {
            $path = '';
        }

        $path = trim($path, '/'); // remove root slash (e.g. '/Projects/xyz/..') and trailing slash (e.g. '../Projects/xyz/')
        
        if ($path === '') {
            return [];
        } else {
            return explode('/', $path);
        }
    }
}
