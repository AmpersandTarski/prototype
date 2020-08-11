<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @var \Slim\Slim $api
 */
global $api;

/**************************************************************************************************
 *
 * resource calls WITHOUT interfaces
 *
 *************************************************************************************************/

/**
 * @phan-closure-scope \Slim\App
 */
$api->group('/file', function () {
    // Inside group closure, $this is bound to the instance of Slim\App
    /** @var \Slim\App $this */

    /**
     * @phan-closure-scope \Slim\Container
     */
    $this->get('/{filePath:.*}', function (Request $request, Response $response, $args = []) {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        $ampersandApp = $this['ampersand_app'];

        $fs = $ampersandApp->fileSystem();
        $filePath = $args['filePath'];
        
        // Check if filePath exists
        if (!$fs->has($filePath)) {
            throw new Exception("File not found", 404);
        }

        $fileResource = $fs->readStream($filePath);
        $stream = new Stream($fileResource); // create a stream instance for the response body

        return $response->withHeader('Content-Description', 'File Transfer')
                        // ->withHeader('Content-Type', $mimeType) // TODO: add mimeType of file
                        ->withHeader('Content-Transfer-Encoding', 'binary')
                        ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"')
                        ->withBody($stream); // all stream contents will be sent to the response
    });
});
