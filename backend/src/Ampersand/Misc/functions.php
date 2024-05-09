<?php

namespace Ampersand\Misc;

use Ampersand\Exception\FatalException;
use Exception;
use League\Flysystem\FilesystemInterface;
use Throwable;

/**
 * Get the filesnames from a folder
 */
function getDirectoryList(string $dirPath): array
{
    $results = []; // create an array to hold directory list
    $handler = opendir($dirPath); // create a handler for the directory
    
    while ($file = readdir($handler)) { // open directory and walk through the filenames
        // if file isn't this directory or its parent, add it to the results
        if ($file != "." && $file != "..") {
            $results[] = $file;
        }
    }
    
    closedir($handler); // tidy up: close the handler
    
    return $results;
}

/**
 * Check if array is sequential (i.e. numeric keys, starting with 0, without gaps)
 */
function isSequential(array $arr): bool
{
    return array_keys($arr) === range(0, count($arr) - 1);
}

/**
 * Returns a file path that does not exists yet
 *
 * Filename is appended with '_i' just before the extension (e.g. dir/file_1.txt)
 */
function getSafeFileName(FilesystemInterface $fileSystem, string $filePath): string
{
    if (!$fileSystem->has($filePath)) {
        return $filePath;
    }

    $dir = pathinfo($filePath, PATHINFO_DIRNAME);
    $filename = pathinfo($filePath, PATHINFO_FILENAME);
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    $i = 1;
    while ($fileSystem->has($dir . DIRECTORY_SEPARATOR . "{$filename}_{$i}.{$ext}")) {
        $i++;
    }
    return $dir . DIRECTORY_SEPARATOR . "{$filename}_{$i}.{$ext}";
}

function stackTrace(Throwable $throwable): string
{
    $html = '<h4>Error/Exception</h4>';
    $html .= sprintf('<div><strong>Type:</strong> %s</div>', get_class($throwable));
    
    if (($code = $throwable->getCode())) {
        $html .= sprintf('<div><strong>Code:</strong> %s</div>', $code);
    }

    if (($message = $throwable->getMessage())) {
        $html .= sprintf('<div><strong>Message:</strong> %s</div>', htmlentities($message));
    }

    if (($file = $throwable->getFile())) {
        $html .= sprintf('<div><strong>File:</strong> %s</div>', $file);
    }

    if (($line = $throwable->getLine())) {
        $html .= sprintf('<div><strong>Line:</strong> %s</div>', $line);
    }

    if (($trace = $throwable->getTraceAsString())) {
        $html .= '<div><strong>Trace:</strong>';
        $html .= sprintf('<pre>%s</pre>', htmlentities($trace));
    }

    // Print stackTrace of previous throwable
    $previous = $throwable->getPrevious();
    if (!is_null($previous)) {
        $html .= stackTrace($previous);
    }

    return $html;
}

/**
 * Function returns a full URL (protocol + host + ..., e.g. https://example.com/test)
 * The $baseUrl is prepended if the $url is not yet a valid URL
 *
 * @throws Exception when resulting url is not valid
 */
function makeValidURL(string $url, ?string $baseUrl = null): string
{
    if (filter_var($url, FILTER_VALIDATE_URL) === true) {
        return $url;
    } else {
        $newUrl = $baseUrl . '/' . $url;

        if (filter_var($newUrl, FILTER_VALIDATE_URL) === false) {
            throw new Exception("Not an valid URL: '{$newUrl}'");
        }

        return $newUrl;
    }
}

/**
 * Returns number of bytes as specified in (string) value
 *
 * This is a utility function to work with php's shorthand notation 'G', 'M' and 'K'
 * See: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
 */
function returnBytes(string|int $value): int
{
    $value = trim($value);

    if (is_numeric($value)) {
        return (int) $value;
    }

    $last = substr($value, -1);
    $val = (int) substr($value, 0, -1);

    switch ($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'G':
            return $val * 1024**3;
        case 'M':
            return $val * 1024**2;
        case 'K':
            return $val * 1024**1;
        default:
            throw new FatalException("Unsupported shorthand notation for number of bytes: '{$value}'");
    }
}

function humanFileSize(int $size, int $decimals = 2): string
{
    if ($size >= 1024**3) {
        return number_format($size/(1024**3), $decimals) . "GB";
    }
    if ($size >= 1024**2) {
        return number_format($size/(1024**2), $decimals) . "MB";
    }
    if ($size >= 1024**1) {
        return number_format($size/(1024**1), $decimals) . "KB";
    }
    return number_format($size, 0) . " bytes";
}
