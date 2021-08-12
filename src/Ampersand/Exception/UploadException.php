<?php

namespace Ampersand\Exception;

use Ampersand\Exception\AmpersandException;

class UploadException extends AmpersandException
{
    /**
     * Instantiate UploadException based on php's upload error codes
     *
     * See: https://www.php.net/manual/en/features.file-upload.errors.php
     * @param int $uploadErrorCode
     */
    public function __construct($uploadErrorCode)
    {
        switch ($uploadErrorCode) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the maximum filesize of " . ini_get('upload_max_filesize');
                $code = 400; // 400 = Bad user request
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the maximum filesize that was specified in the HTML form";
                $code = 400; // 400 = Bad user request
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                $code = 400; // 400 = Bad user request
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                $code = 400; // 400 = Bad user request
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                $code = 500; // 500 = Server error
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                $code = 500; // 500 = Server error
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by a php extension. See php.net";
                $code = 500; // 500 = Server error
                break;
            default:
                $message = "Unknown upload error";
                $code = 500; // 500 = Server error
                break;
        }
        
        parent::__construct($message, $code);
    }
}
