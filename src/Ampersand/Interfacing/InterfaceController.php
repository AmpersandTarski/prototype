<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Exception;
use Ampersand\AmpersandApp;
use Ampersand\AngularApp;
use Ampersand\Interfacing\Resource;
use function Ampersand\Misc\getSafeFileName;

class InterfaceController
{

    /**
     * Reference to the ampersand backend instance
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $ampersandApp;

    /**
     * Reference to the frontend instance
     *
     * @var \Ampersand\AngularApp
     */
    protected $angularApp;

    /**
     * Constructor
     *
     * @param \Ampersand\AmpersandApp $ampersandApp
     * @param \Ampersand\AngularApp $angularApp
     */
    public function __construct(AmpersandApp $ampersandApp, AngularApp $angularApp)
    {
        $this->ampersandApp = $ampersandApp;
        $this->angularApp = $angularApp;
    }

    public function get(Resource $resource, $ifcPath, int $options, $depth)
    {
        $resourcePath = new ResourcePath($resource, $ifcPath);
        
        if ($resourcePath->hasTrailingIfc()) {
            throw new Exception("Provided path '{$resourcePath}' MUST end with a resource identifier", 400);
        }

        return $resourcePath->getTgt()->get($options, $depth);
    }

    public function put(Resource $resource, $ifcPath, $body, $options, $depth): array
    {
        $transaction = $this->ampersandApp->newTransaction();
        
        // Perform put
        $resourcePath = new ResourcePath($resource, $ifcPath);
        if ($resourcePath->hasTrailingIfc()) {
            throw new Exception("Provided path '{$resourcePath}' MUST end with a resource identifier", 400);
        }
        $resource = $resourcePath->getTgt()->put($body);
        
        // Run ExecEngine
        $transaction->runExecEngine();

        // Get content to return
        try {
            $content = $resource->get($options, $depth);
        } catch (Exception $e) { // e.g. when read is not allowed
            $content = $body;
        }

        // Close transaction
        $transaction->close();
        if ($transaction->isCommitted()) {
            $this->ampersandApp->userLog()->notice($resource->getLabel() . " updated");
        }
        
        $this->ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles

        // Return result
        return [ 'content'               => $content
               , 'notifications'         => $this->ampersandApp->userLog()->getAll()
               , 'invariantRulesHold'    => $transaction->invariantRulesHold()
               , 'isCommitted'           => $transaction->isCommitted()
               , 'sessionRefreshAdvice'  => $this->angularApp->getSessionRefreshAdvice()
               , 'navTo'                 => $this->angularApp->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
               ];
    }

    /**
     * Patch resource with provided patches
     * Use JSONPatch specification for $patches (see: http://jsonpatch.com/)
     *
     * @param \Ampersand\Interfacing\Resource $resource
     * @param string|array $ifcPath
     * @param array $patches
     * @param int $options
     * @param int|null $depth
     * @return array
     */
    public function patch(Resource $resource, $ifcPath, array $patches, int $options, int $depth = null): array
    {
        $transaction = $this->ampersandApp->newTransaction();
        
        // Perform patch(es)
        $resourcePath = new ResourcePath($resource, $ifcPath);
        if ($resourcePath->hasTrailingIfc()) {
            throw new Exception("Provided path '{$resourcePath}' MUST end with a resource identifier", 400);
        }
        $resource = $resourcePath->getTgt()->patch($patches);

        // Run ExecEngine
        $transaction->runExecEngine();

        // Get content to return
        try {
            $content = $resource->get($options, $depth);
        } catch (Exception $e) { // e.g. when read is not allowed
            $content = null;
        }

        // Close transaction
        $transaction->close();
        if ($transaction->isCommitted()) {
            $this->ampersandApp->userLog()->notice($resource->getLabel() . " updated");
        }
        
        $this->ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
    
        // Return result
        return [ 'patches'               => $patches
               , 'content'               => $content
               , 'notifications'         => $this->ampersandApp->userLog()->getAll()
               , 'invariantRulesHold'    => $transaction->invariantRulesHold()
               , 'isCommitted'           => $transaction->isCommitted()
               , 'sessionRefreshAdvice'  => $this->angularApp->getSessionRefreshAdvice()
               , 'navTo'                 => $this->angularApp->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
               ];
    }

    public function post(Resource $resource, $ifcPath, $body, $options, $depth): array
    {
        $transaction = $this->ampersandApp->newTransaction();

        // Perform POST
        $resourcePath = new ResourcePath($resource, $ifcPath);
        if (!$resourcePath->hasTrailingIfc()) {
            throw new Exception("Provided path '{$resourcePath}' MUST NOT end with a resource identifier", 400);
        }
        $resource = $resourcePath->getTgt()->post($resourcePath->getTrailingIfc(), $body);

        // Run ExecEngine
        $transaction->runExecEngine();

        // Get content to return
        try {
            $content = $resource->get($options, $depth);
        } catch (Exception $e) { // e.g. when read is not allowed
            $content = $body;
        }

        // Close transaction
        $transaction->close();
        if ($transaction->isCommitted()) {
            Logger::getUserLogger()->notice($resource->getLabel() . " created");
        } else {
            // TODO: remove possible uploaded file
        }
        
        $this->ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
    
        // Return result
        return [ 'content'               => $content
               , 'notifications'         => $this->ampersandApp->userLog()->getAll()
               , 'invariantRulesHold'    => $transaction->invariantRulesHold()
               , 'isCommitted'           => $transaction->isCommitted()
               , 'sessionRefreshAdvice'  => $this->angularApp->getSessionRefreshAdvice()
               , 'navTo'                 => $this->angularApp->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
               ];
    }

    /**
     * Delete a resource given an entry resource and a path
     *
     * @param \Ampersand\Interfacing\Resource $resource
     * @param string|array $ifcPath
     * @return array
     */
    public function delete(Resource $resource, $ifcPath): array
    {
        $transaction = $this->ampersandApp->newTransaction();
        
        // Perform delete
        $resourcePath = new ResourcePath($resource, $ifcPath);
        if ($resourcePath->hasTrailingIfc()) {
            throw new Exception("Provided path '{$resourcePath}' MUST end with a resource identifier", 400);
        }
        $resource = $resourcePath->getTgt()->delete();
        
        // Close transaction
        $transaction->runExecEngine()->close();
        if ($transaction->isCommitted()) {
            $this->ampersandApp->userLog()->notice("Resource deleted");
        }
        
        $this->ampersandApp->checkProcessRules(); // Check all process rules that are relevant for the activate roles
        
        // Return result
        return [ 'notifications'         => $this->ampersandApp->userLog()->getAll()
               , 'invariantRulesHold'    => $transaction->invariantRulesHold()
               , 'isCommitted'           => $transaction->isCommitted()
               , 'sessionRefreshAdvice'  => $this->angularApp->getSessionRefreshAdvice()
               , 'navTo'                 => $this->angularApp->getNavToResponse($transaction->isCommitted() ? 'COMMIT' : 'ROLLBACK')
               ];
    }
}
