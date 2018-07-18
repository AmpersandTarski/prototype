<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use stdClass;
use Exception;
use ArrayIterator;
use IteratorAggregate;
use Ampersand\Core\Atom;
use Ampersand\Log\Logger;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\Resource;
use Ampersand\Interfacing\InterfaceExprObject;
use Ampersand\Interfacing\InterfaceObjectInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ResourceList
{
    
    /**
    *
    * @var \Psr\Log\LoggerInterface
    */
    protected $logger;
    
    /**
     * The source Resource (i.e. Atom) of this resource list
     *
     * @var \Ampersand\Interfacing\Resource
     */
    protected $src = null;
    
    /**
     * The Interface that is the base of this resource list
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface
     */
    protected $ifc = null;
    
    /**
     * List with target resources
     *
     * @var \Ampersand\Interfacing\Resource[]
     */
    protected $tgts = null;
    
    /**
     * Constructor
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @param \Ampersand\Interfacing\InterfaceExprObject $ifc
     * @param bool $skipAccessCheck
     */
    public function __construct(Resource $src, InterfaceExprObject $ifc, bool $skipAccessCheck = false)
    {
        /** @var \Pimple\Container $container */
        global $container; // TODO: remove dependency on global $container var
        $this->logger = Logger::getLogger('INTERFACING');
        
        if ($ifc->isRoot() && !$container['ampersand_app']->isAccessibleIfc($ifc) && !$skipAccessCheck) {
            throw new Exception("Unauthorized to access interface {$ifc}", 403);
        }
        
        // Epsilon. TODO: remove after multiple concept specifications are possible for Atom objects
        if ($src->concept !== $ifc->srcConcept) {
            $this->src = clone $src;
            $this->src->concept = $ifc->srcConcept;
        // No epsilon
        } else {
            $this->src = $src;
        }
        
        $this->ifc = $ifc;
    }
    
    /**
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public function getIfc(): InterfaceObjectInterface
    {
        return $this->ifc;
    }
    
}
