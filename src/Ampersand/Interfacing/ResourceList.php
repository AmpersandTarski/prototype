<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Interfacing\Ifc;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Resource;
use Ampersand\Core\Concept;
use Ampersand\Exception\AtomNotFoundException;
use Exception;
use stdClass;
use function Ampersand\Misc\getSafeFileName;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ResourceList
{
    /**
     * Undocumented variable
     *
     * @var \Ampersand\Core\Atom
     */
    protected $srcAtom;

    /**
     * Undocumented variable
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface
     */
    protected $ifcObject;

    /**
     * Path to $srcAtom
     *
     * @var string
     */
    protected $pathEntry;

    public function __construct(Atom $srcAtom, InterfaceObjectInterface $ifcObj, string $pathEntry)
    {
        $this->srcAtom = $srcAtom;
        $this->ifcObject = $ifcObj;
        $this->pathEntry = $pathEntry;
    }

    public function getResourcePath(Resource $tgt): string
    {
        return $this->ifcObject->buildResourcePath($tgt, $this->pathEntry);
    }

    public function getIfcObject(): InterfaceObjectInterface
    {
        return $this->ifcObject;
    }

    public function isUni(): bool
    {
        return $this->ifcObject->isUni();
    }

    /**********************************************************************************************
     * Methods to navigate through list
     *********************************************************************************************/

    public function one(string $tgtId): Resource
    {
        $tgts = $this->ifcObject->getTgtAtoms($this->srcAtom, $tgtId);

        if (!empty($tgts)) {
            // Resource found
            return $this->makeResource(current($tgts));
        // Temporary fix for #884. TODO: remove this elseif clause when solution is implemented
        } elseif ($this->ifcObject->crudC() && !$this->tgtIdInPath()) {
            return $this->create($tgtId);
        } else {
            // When not found
            throw new AtomNotFoundException("Resource '{$tgtId}' not found");
        }
    }

    /**
     * Undocumented function
     *
     * @return \Ampersand\Interfacing\Resource[]
     */
    public function getResources(): array
    {
        // Convert tgt Atoms into Resources
        return array_map(function (Atom $atom) {
            return $this->makeResource($atom);
        }, $this->ifcObject->getTgtAtoms($this->srcAtom));
    }

    /**
     * Undocumented function
     *
     * @param array $pathList
     * @return \Ampersand\Interfacing\Resource|\Ampersand\Interfacing\ResourceList
     */
    public function walkPath(array $pathList)
    {
        if (empty($pathList) && $this->tgtIdInPath()) {
            return $this;
        }

        $tgtId = $this->tgtIdInPath() ? array_shift($pathList) : $this->srcAtom->getId();
        
        return $this->one($tgtId)->walkPath($pathList);
    }

    public function walkPathToResource(array $pathList): Resource
    {
        if (empty($pathList) && $this->tgtIdInPath()) {
            throw new Exception("Provided path MUST end with a resource identifier", 400);
        }

        $tgtId = $this->tgtIdInPath() ? array_shift($pathList) : $this->srcAtom->getId();

        return $this->one($tgtId)->walkPathToResource($pathList);
    }

    public function walkPathToList(array $pathList): ResourceList
    {
        if (empty($pathList)) {
            return $this;
        }
        
        $tgtId = $this->tgtIdInPath() ? array_shift($pathList) : $this->srcAtom->getId();

        return $this->one($tgtId)->walkPathToList($pathList);
    }

    protected function tgtIdInPath(): bool
    {
        /* Skip resource id for ident interface expressions (I[Concept])
        * I expressions are commonly used for adding structure to an interface using (sub) boxes
        * This results in verbose paths
        * e.g.: pathToApi/resource/Person/John/PersonIfc/John/PersonDetails/John/Name
        * By skipping ident expressions the paths are more concise without loosing information
        * e.g.: pathToApi/resource/Person/John/PersonIfc/PersonDetails/Name
        */
        return !$this->ifcObject->isIdent();
    }

    /**********************************************************************************************
     * REST methods to call on resource list
     *********************************************************************************************/

    public function get(int $options = Options::DEFAULT_OPTIONS, int $depth = null)
    {
        return $this->ifcObject->read($this->srcAtom, $this->pathEntry, null, $options, $depth);
    }

    public function getOne(string $tgtId, int $options = Options::DEFAULT_OPTIONS, int $depth = null)
    {
        return $this->ifcObject->read($this->srcAtom, $this->pathEntry, $tgtId, $options, $depth);
    }

    public function post(stdClass $resourceToPost = null): Resource
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        $newResource = $this->makeResource($this->ifcObject->create($this->srcAtom));

        // Special case for file upload
        if ($newResource->concept->isFileObject()) {
            if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                $fs = $ampersandApp->fileSystem();
                
                $tmp_name = $_FILES['file']['tmp_name'];
                $originalFileName = $_FILES['file']['name'];
                $filePath = "uploads/{$originalFileName}";

                // Make filePath safe (i.e. valid path and non-existing)
                $filePath = getSafeFileName($fs, $filePath);
                
                $result = move_uploaded_file($tmp_name, $filePath);
                if (!$result) {
                    throw new Exception("Error in file upload", 500);
                }
                
                // Populate filePath and originalFileName relations in database
                $newResource->link($filePath, 'filePath[FileObject*FilePath]')->add();
                $newResource->link($originalFileName, 'originalFileName[FileObject*FileName]')->add();
            } else {
                throw new Exception("No file uploaded", 400);
            }
            return $newResource;
        // Regular case
        } else {
            // Put resource attributes
            return $newResource->put($resourceToPost);
        }
    }

    /**********************************************************************************************
     * Internal methods
     *********************************************************************************************/

    /**
     * Undocumented function
     *
     * @param string|bool|null $value
     * @return \Ampersand\Interfacing\Resource|null
     */
    public function set($value = null): ?Resource
    {
        $tgt = $this->ifcObject->set($this->srcAtom, $value);
        if (is_null($tgt)) {
            return null;
        } else {
            return $this->makeResource($tgt);
        }
    }

    public function add($value): Resource
    {
        return $this->makeResource($this->ifcObject->add($this->srcAtom, $value));
    }

    public function create(string $tgtId): Resource
    {
        return $this->makeResource($this->ifcObject->create($this->srcAtom, $tgtId));
    }

    public function remove(string $value): void
    {
        $this->ifcObject->remove($this->srcAtom, $value);
    }

    public function removeAll(): void
    {
        $this->ifcObject->removeAll($this->srcAtom);
    }

    protected function makeResource(Atom $atom): Resource
    {
        return new Resource($atom->getId(), $atom->concept, $this);
    }

    /**********************************************************************************************
     * STATIC METHODS
     *********************************************************************************************/

    /**
     * Instantiate resource list with given src atom and interface
     *
     * @param string $srcAtomId
     * @param string $ifcIdOrLabel
     * @return \Ampersand\Interfacing\ResourceList
     */
    public static function makeFromInterface(string $srcAtomId, string $ifcIdOrLabel): ResourceList
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        $ifc = $ampersandApp->getModel()->getInterface($ifcIdOrLabel, true);
        $srcAtom = $ifc->getSrcConcept()->makeAtom($srcAtomId);

        // Same as in InterfaceNullObject::buildResourcePath()
        if ($srcAtom->concept->isSession()) {
            $pathEntry = "resource/SESSION/1"; // Don't put session id here, this is implicit
        } else {
            $pathEntry = "resource/{$srcAtom->concept->name}/{$srcAtom->getId()}";
        }

        return new ResourceList($srcAtom, $ifc->getIfcObject(), $pathEntry);
    }

    /**
     * Instantiate resource list for a given resource type (i.e. concept)
     *
     * @param \Ampersand\Core\Concept $concept
     * @return \Ampersand\Interfacing\ResourceList
     */
    public static function makeWithoutInterface(Concept $concept): ResourceList
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var

        $one = $ampersandApp->getModel()->getConcept('ONE')->makeAtom('ONE');
        return new ResourceList($one, Ifc::getNullObject($concept, $ampersandApp), '');
    }
}
