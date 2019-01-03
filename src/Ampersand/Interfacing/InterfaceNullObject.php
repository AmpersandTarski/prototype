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
use Ampersand\Interfacing\AbstractIfcObject;
use Ampersand\Core\Concept;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceNullObject extends AbstractIfcObject implements InterfaceObjectInterface
{
    /**
     * The target concept of this interface object
     *
     * @var \Ampersand\Core\Concept
     */
    protected $tgtConcept;

    public function __construct(string $tgtConcept)
    {
        $this->tgtConcept = Concept::getConcept($tgtConcept);
    }

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
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var

        // Prevent users to create (other) sessions in any case
        if ($this->tgtConcept->isSession()) {
            return false;
        }
        // Prevent create for non-object (i.e. scalar) values
        if (!$this->tgtConcept->isObject()) {
            return false;
        }

        // Allow when there is at least an interface accesible for the user to create a new tgt
        foreach ($ampersandApp->getAccessibleInterfaces() as $ifc) {
            $ifcObj = $ifc->getIfcObject();
            if ($ifcObj->crudC() && $ifcObj->tgtConcept === $this->tgtConcept) {
                return true;
            }
        }
        
        return false;
    }

    public function crudR(): bool
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var

        // Checks
        if ($this->tgtConcept->isSession()) {
            return false; // Prevent users to list (other) sessions in any case
        }
        if (!$this->tgtConcept->isObject()) {
            return false; // Prevent listing of non-object (i.e. scalar) values
        }
        if ($ampersandApp->isEditableConcept($this->tgtConcept)) {
            return true;
        }
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
     * Returns list of target atoms
     *
     * @param \Ampersand\Core\Atom $src
     * @return \Ampersand\Core\Atom[]
     */
    public function getTgtAtoms(Atom $src, string $selectTgt = null): array
    {
        // Skip access when selectTgt is provided, because we don't know the interface that is requested (yet)
        // This is checked later when reading or walking the path further.
        if (is_null($selectTgt) && !$this->crudR()) {
            throw new Exception("You do not have access for this call", 403);
        }

        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var

        // Make sure that only the current session of the user can be selected
        if ($this->tgtConcept->isSession()) {
            $selectTgt = $ampersandApp->getSession()->getSessionAtom()->id;
        }

        if (isset($selectTgt)) {
            $tgt = new Atom($selectTgt, $this->tgtConcept);
            // For the InterfaceNullObject, let's assume the atom exists, otherwise
            // an exception 'Resource not found' is returned by ResourceList class,
            // which exposes information while we haven't checked access using an interface yet.
            // Existance is checked later when reading or walking the path further
            return [$tgt];
        } else {
            return $this->tgtConcept->getAllAtomObjects();
        }
    }

    /**
     * Returns path for given tgt atom
     *
     * @param \Ampersand\Core\Atom $tgt
     * @param string $pathToSrc
     * @return string
     */
    public function buildResourcePath(Atom $tgt, string $pathToSrc): string
    {
        if ($tgt->concept->isSession()) {
            return "resource/SESSION/1"; // Don't put real session id here. Instead we use '1' to indicate the user session
        } else {
            return "resource/{$tgt->concept->name}/{$tgt->id}";
        }
    }

    /**********************************************************************************************
     * Sub interface objects METHODS
     *********************************************************************************************/

    /**
     * Undocumented function
     *
     * @param int $options
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS): array
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var

        $tgtConcept = $this->tgtConcept;

        $ifcs = array_filter(
            $ampersandApp->getAccessibleInterfaces(),
            function (Ifc $ifc) use ($tgtConcept) {
                return $this->tgtConcept->hasGeneralization($ifc->getSrcConcept(), true);
            }
        );

        return array_map(function (Ifc $ifc) {
            return $ifc->getIfcObject();
        }, $ifcs);
    }

    public function hasSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): bool
    {
        return in_array($ifcId, $this->getSubinterfaces());
    }

    public function getSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        foreach ($this->getSubinterfaces($options) as $ifcobj) {
            /** @var \Ampersand\Interfacing\InterfaceObjectInterface $ifcobj */
            if ($ifcobj->getIfcId() === $ifcId) {
                return $ifcobj;
            }
        }

        // Not found
        throw new Exception("Unauthorized to access or interface does not exist '{$ifcId}'", 403);
    }

    public function getSubinterfaceByLabel(string $ifcLabel, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        foreach ($this->getSubinterfaces($options) as $ifcobj) {
            /** @var \Ampersand\Interfacing\InterfaceObjectInterface $ifcobj */
            if ($ifcobj->getIfcLabel() === $ifcLabel) {
                return $ifcobj;
            }
        }

        // Not found
        throw new Exception("Unauthorized to access or interface does not exist '{$ifcLabel}'", 403);
    }

    /**********************************************************************************************
     * CRUD METHODS
     *********************************************************************************************/
    public function create(Atom $src, $tgtId = null): Atom
    {
        if (!$this->crudC()) {
            throw new Exception("You do not have access for this call", 403);
        }

        // Make new resource
        if (isset($tgtId)) {
            $tgtAtom = new Atom($tgtId, $this->tgtConcept);
            if ($tgtAtom->exists()) {
                throw new Exception("Cannot create resource that already exists", 400);
            }
        } else {
            $tgtAtom = $this->tgtConcept->createNewAtom();
        }

        // Add to plug (e.g. database)
        return $tgtAtom->add();
    }

    public function read(Atom $src, string $pathToSrc, string $tgtId = null, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = [])
    {
        if (!$this->crudR()) {
            throw new Exception("You do not have access for this call", 403);
        }

        // Init result array
        $result = [];

        foreach ($this->getTgtAtoms($src, $tgtId) as $tgt) {
            // Basic UI data of a resource
            if ($options & Options::INCLUDE_UI_DATA) {
                $resource = [];
                $viewData = $tgt->concept->getViewData($tgt); // default concept view

                // Add Ampersand atom attributes
                $resource['_id_'] = $tgt->id;
                $resource['_label_'] = empty($viewData) ? $tgt->getLabel() : implode('', $viewData);
                $resource['_path_'] = $this->buildResourcePath($tgt, $pathToSrc);
            
                // Add view data if array is assoc (i.e. not sequential, because then it is a label)
                if (!isSequential($viewData)) {
                    $resource['_view_'] = $viewData;
                }

                $result[] = $resource;
            } else {
                $result[] = $tgt->id;
            }
        }

        // Return result
        if (isset($tgtId)) { // single object
            return empty($result) ? null : current($result);
        } else { // array
            return $result;
        }
    }

    public function set(Atom $src, $value = null): ?Atom
    {
        throw new Exception("No interface specified", 405);
    }

    public function add(Atom $src, $value): Atom
    {
        throw new Exception("No interface specified", 405);
    }

    public function remove(Atom $src, $value): void
    {
        throw new Exception("No interface specified", 405);
    }

    public function removeAll(Atom $src): void
    {
        throw new Exception("No interface specified", 405);
    }

    public function delete(Resource $tgtAtom): void
    {
        throw new Exception("No interface specified", 405);
    }

    /**********************************************************************************************
     * HELPER METHODS
     *********************************************************************************************/

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
