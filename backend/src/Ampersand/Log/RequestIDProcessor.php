<?php

namespace Ampersand\Log;

/**
 * Adds same generated value into every record within a request
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 */
class RequestIDProcessor
{
    /**
     * Request identifier
     */
    protected string $requestID;
    
    public function __construct()
    {
        $this->requestID = bin2hex(random_bytes(5));
    }

    // public function __invoke(array $record): array
    // {
    //     $record['extra']['request_id'] = $this->requestID;

    //     return $record;
    // }

    public function __invoke($record)
    {  // Handle both array (Monolog 1.x/2.x) and LogRecord (Monolog 3.x)
        if ($record instanceof LogRecord) {
            // For Monolog 3.x
            $record = $record->with('extra', array_merge(
                $record->extra, ['request_id' => $this->requestId]
            ));                                                                                                            
        } else {
            // For Monolog 1.x/2.x                                                                                             
            if (!isset($record['extra'])) {
                $record['extra'] = [];                                                                                         
            }
            $record['extra']['request_id'] = $this->requestID;
        }
        return $record;                                                                                                
    }
}
