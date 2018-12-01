<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Interfacing\Resource;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\Ifc;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceNullObject implements InterfaceObjectInterface
{
    public function getSubinterface(string $ifcId, bool $skipAccessCheck = false): InterfaceObjectInterface
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        $ifc = Ifc::getInterface($ifcId);

        if (!$ampersandApp->isAccessibleIfc($ifc) && !$skipAccessCheck) {
            throw new Exception("Unauthorized to access interface {$ifc}", 403);
        }

        return $ifc->getIfcObject();
    }

    public function getViewData(Atom $tgtAtom): array
    {
        return $tgtAtom->concept->getViewData($tgtAtom);
    }

    public function read(Resource $src, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = [])
    {
        // User interface data (_id_, _label_ and _view_ and _path_)
        if ($options & Options::INCLUDE_UI_DATA) {
            $content = [];

            // Add Ampersand atom attributes
            $content['_id_'] = $src->id;
            $content['_label_'] = $src->getLabel();
            $content['_path_'] = $src->getPath();
        
            // Add view data if array is assoc (i.e. not sequential)
            $data = $src->getView();
            if (!isSequential($data)) {
                $content['_view_'] = $data;
            }
            return $content;
        } else {
            return $src->id;
        }
    }

    public function delete(Resource $tgtAtom): bool
    {
        throw new Exception("Cannot perform delete without interface specification", 400);
    }
}
