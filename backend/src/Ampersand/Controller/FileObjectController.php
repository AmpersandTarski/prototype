<?php

namespace Ampersand\Controller;

use Ampersand\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class FileObjectController extends AbstractController
{
    public function getFile(Request $request, Response $response, array $args): Response
    {
        $fs = $this->app->fileSystem();
        $filePath = $args['filePath'];
        
        // Check if filePath exists
        if (!$fs->fileExists($filePath)) {
            throw new NotFoundException("File not found");
        }

        $fileResource = $fs->readStream($filePath);
        $stream = new Stream($fileResource); // create a stream instance for the response body
        $mimeType = $fs->mimeType($filePath);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream'; // the "octet-stream" subtype is used to indicate that a body contains arbitrary binary data.
        }

        return $response->withHeader('Content-Description', 'File Transfer')
                        ->withHeader('Content-Type', $mimeType)
                        ->withHeader('Content-Transfer-Encoding', 'binary')
                        // ->withHeader('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"') // enable this to force browser to download the file
                        ->withBody($stream); // all stream contents will be sent to the response
    }
}
