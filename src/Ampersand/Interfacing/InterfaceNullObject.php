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

        if (!$this->tgtConcept->isObject()) {
            throw new Exception("InterfaceNullObject is not applicable for non-object concept {$tgtConcept}.", 500);
        }
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

    public function getTargetConcept(): Concept
    {
        return $this->tgtConcept;
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

        // Check if allowed (i.e. there is at least an interface accesible for the user to create a new tgt)
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
            return false; // Prevent users to list (other) sessions
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
        if (!$this->crudR()) {
            throw new Exception("You do not have access for this call", 403);
        }

        if (isset($selectTgt)) {
            $tgt = Atom::makeAtom($selectTgt, $this->tgtConcept->getId());
            return $tgt->exists() ? [$tgt] : []; // If tgt not exists, return empty array
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

    public function hasSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): bool
    {
        return Ifc::interfaceExists($ifcId);
    }

    public function getSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        $ifc = Ifc::getInterface($ifcId);

        if (!$ampersandApp->isAccessibleIfc($ifc)) {
            throw new Exception("Unauthorized to access interface {$ifc->getLabel()}", 403);
        }

        return $ifc->getIfcObject();
    }

    public function getSubinterfaceByLabel(string $ifcLabel, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
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
