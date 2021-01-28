<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

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
        $mimeType = $fs->getMimetype($filePath);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream'; // the "octet-stream" subtype is used to indicate that a body contains arbitrary binary data.
        }

        return $response->withHeader('Content-Description', 'File Transfer')
                        ->withHeader('Content-Type', $mimeType)
                        ->withHeader('Content-Transfer-Encoding', 'binary')
                        // ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"') // enable this to force browser to download the file
                        ->withBody($stream); // all stream contents will be sent to the response
    });
});
