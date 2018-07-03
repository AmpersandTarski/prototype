<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs;

use Ampersand\Core\Atom;
use Ampersand\Interfacing\InterfaceObjectInterface;

/**
 * Interface for a plug for an InterfaceObjectInterface
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
interface IfcPlugInterface extends PlugInterface
{
    
    /**
     * @param \Ampersand\Interfacing\InterfaceObjectInterface $ifc
     * @param \Ampersand\Core\Atom $srcAtom
     * @return mixed
     */
    public function executeIfcExpression(InterfaceObjectInterface $ifc, Atom $srcAtom);
}
