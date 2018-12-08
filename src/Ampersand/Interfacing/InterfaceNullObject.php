<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Interfacing\Resource;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\Ifc;
use Exception;
use function Ampersand\Misc\isSequential;
use Ampersand\Core\Atom;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceNullObject implements InterfaceObjectInterface
{
    public function __toString(): string
    {
        return "InterfaceNullObject";
    }

    public function getIfcId(): string
    {
        return "InterfaceNullObject";
    }
    
    public function getIfcLabel(): string
    {
        return "InterfaceNullObject";
    }

    public function getEditableConcepts()
    {
        return [];
    }

    public function isIdent(): bool
    {
        return false;
    }

    public function isUni(): bool
    {
        return false;
    }

    public function getPath(): string
    {
        return '';
    }

    public function crudC(): bool
    {
        return false;
    }

    public function crudR(): bool
    {
        return false;
    }

    public function crudU(): bool
    {
        return false;
    }

    public function crudD(): bool
    {
        return false;
    }

    /**********************************************************************************************
     * METHODS to walk through interface
     *********************************************************************************************/
    /**
     * Returns specific target atom as Resource object
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @param string $tgtId
     * @return \Ampersand\Interfacing\Resource
     */
    public function one(Resource $src, string $tgtId): Resource
    {
        throw new Exception("N.a.: method InterfaceNullObject::one() SHOULD not be called", 500);
    }

    /**
     * Returns list of target atoms
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @return \Ampersand\Interfacing\Resource[]
     */
    public function all(Resource $src): array
    {
        throw new Exception("N.a.: method InterfaceNullObject::all() SHOULD not be called", 500);
    }

    /**
     * Returns path for given tgt resource
     *
     * @param \Ampersand\Interfacing\Resource $tgt
     * @param \Ampersand\Interfacing\Resource|null $parent
     * @return string
     */
    public function buildResourcePath(Resource $tgt, Resource $parent = null): string
    {
        if ($tgt->concept->isSession()) {
            return "session"; // Don't put session id here, this is implicit
        } else {
            return "resource/{$tgt->concept->name}/{$tgt->id}";
        }
    }

    /**********************************************************************************************
     * Sub interface objects METHODS
     *********************************************************************************************/

    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS): array
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var

        return array_map(function (Ifc $ifc) {
            return $ifc->getIfcObject();
        }, $ampersandApp->getAccessibleInterfaces());
    }

    public function hasSubinterface(string $ifcId): bool
    {
        return Ifc::interfaceExists($ifcId);
    }

    public function getSubinterface(string $ifcId): InterfaceObjectInterface
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        $ifc = Ifc::getInterface($ifcId);

        if (!$ampersandApp->isAccessibleIfc($ifc)) {
            throw new Exception("Unauthorized to access interface {$ifc->getLabel()}", 403);
        }

        return $ifc->getIfcObject();
    }

    public function getSubinterfaceByLabel(string $ifcLabel): InterfaceObjectInterface
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        $ifc = Ifc::getInterfaceByLabel($ifcLabel);

        if (!$ampersandApp->isAccessibleIfc($ifc)) {
            throw new Exception("Unauthorized to access interface {$ifc->getLabel()}", 403);
        }

        return $ifc->getIfcObject();
    }

    /**********************************************************************************************
     * CRUD METHODS
     *********************************************************************************************/
    public function create(Resource $src, $tgtId = null): Resource
    {
        throw new Exception("No interface specified", 405);
    }

    public function read(Resource $src, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = [])
    {
        // Init content array
        $content = [];

        // Basic UI data of a resource
        if ($options & Options::INCLUDE_UI_DATA) {
            $viewData = $src->concept->getViewData($src); // default concept view

            // Add Ampersand atom attributes
            $content['_id_'] = $src->id;
            $content['_label_'] = empty($viewData) ? $src->getLabel() : implode('', $viewData);
            $content['_path_'] = $src->getPath();
        
            // Add view data if array is assoc (i.e. not sequential, because then it is a label)
            if (!isSequential($viewData)) {
                $content['_view_'] = $viewData;
            }
        } else {
            return $src->id;
        }
    }

    public function set(Atom $src, $value = null): bool
    {
        throw new Exception("No interface specified", 405);
    }

    public function add(Atom $src, $value): bool
    {
        throw new Exception("No interface specified", 405);
    }

    public function remove(Atom $src, $value): bool
    {
        throw new Exception("No interface specified", 405);
    }

    public function removeAll(Atom $src): bool
    {
        throw new Exception("No interface specified", 405);
    }

    public function delete(Resource $tgtAtom): bool
    {
        throw new Exception("No interface specified", 405);
    }

    /**********************************************************************************************
     * HELPER METHODS
     *********************************************************************************************/
    
    /**
     * Return list of all sub interface objects recursively (incl. the current object itself)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getIfcObjFlattened(): array
    {
        $arr = [$this];
        foreach ($this->getSubinterfaces(Options::DEFAULT_OPTIONS & ~Options::INCLUDE_REF_IFCS) as $subObj) {
            $arr = array_merge($arr, $subObj->getIfcObjFlattened());
        }
        return $arr;
    }

    /**
     * Return properties of interface object
     *
     * @return array
     */
    public function getTechDetails(): array
    {
        return [];
    }

    /**
     * Return diagnostic information of interface object
     *
     * @return array
     */
    public function diagnostics(): array
    {
        return [];
    }
}
