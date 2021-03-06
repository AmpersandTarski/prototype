<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Plugs;

use Ampersand\Core\Atom;
use Ampersand\Interfacing\InterfaceExprObject;

/**
 * Interface for a plug for an InterfaceExprObject
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
interface IfcPlugInterface extends PlugInterface
{
    
    /**
     * @param \Ampersand\Interfacing\InterfaceExprObject $ifc
     * @param \Ampersand\Core\Atom $srcAtom
     * @return mixed
     */
    public function executeIfcExpression(InterfaceExprObject $ifc, Atom $srcAtom);
}
