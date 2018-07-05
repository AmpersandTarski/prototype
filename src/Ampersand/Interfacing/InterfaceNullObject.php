<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Interfacing\Resource;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceNullObject implements InterfaceObjectInterface
{
    public function getViewData(Atom $tgtAtom): array
    {
        return $tgtAtom->concept->getViewData($tgtAtom);
    }

    public function put(Resource $tgtAtom, $value): bool
    {
        throw new Exception("Cannot perform put without interface specification", 400);
    }

    public function delete(Resource $tgtAtom): bool
    {
        throw new Exception("Cannot perform delete without interface specification", 400);
    }
}
