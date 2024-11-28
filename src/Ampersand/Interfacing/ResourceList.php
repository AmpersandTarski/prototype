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
use Ampersand\Exception\AmpersandException;
use Ampersand\Exception\AtomNotFoundException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\UploadException;
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
     */
    protected Atom $srcAtom;

    /**
     * Undocumented variable
     */
    protected InterfaceObjectInterface $ifcObject;

    /**
     * Path to src atom
     */
    protected string $pathEntry;

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

    public function one(?string $tgtId = null): Resource
    {
        $tgts = $this->ifcObject->getTgtAtoms($this->srcAtom, $tgtId);

        // Resource found
        if (!empty($tgts)) {
            return $this->makeResource(current($tgts));
        }
        
        // Auto create when allowed
        if (!is_null($tgtId) && $this->ifcObject->crudC()) {
            return $this->create($tgtId);
        }
        
        // Not found
        $msg = is_null($tgtId) ? "Resource not found" : "Resource '{$tgtId}' not found";
        throw new AtomNotFoundException($msg);
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
            return $this->makeResource($atom)->setQueryData($atom->getQueryData()); // make sure that query data is preserved for optimization;
        }, $this->ifcObject->getTgtAtoms($this->srcAtom));
    }

    /**
     * Undocumented function
     */
    public function walkPath(array $pathList): Resource|ResourceList
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
            throw new BadRequestException("Provided path MUST end with a resource identifier");
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

    public function get(int $options = Options::DEFAULT_OPTIONS, ?int $depth = null): mixed
    {
        return $this->ifcObject->read($this->srcAtom, $this->pathEntry, null, $options, $depth);
    }

    public function getOne(string $tgtId, int $options = Options::DEFAULT_OPTIONS, ?int $depth = null): mixed
    {
        return $this->ifcObject->read($this->srcAtom, $this->pathEntry, $tgtId, $options, $depth);
    }

    public function post(?stdClass $resourceToPost = null): Resource
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        $newResource = $this->makeResource($this->ifcObject->create($this->srcAtom));

        // Special case for file upload
        if ($newResource->concept->isFileObject()) {
            // Check if file is specified. This is not the case e.g. when post_max_size is exceeded
            // Maximum post size is checked in generic API middleware function
            if (!isset($_FILES['file'])) {
                throw new BadRequestException("No file(s) provided to upload");
            }

            $fileInfo = $_FILES['file'];
            
            if (is_uploaded_file($fileInfo['tmp_name'])) {
                $fs = $ampersandApp->fileSystem();
                
                $tmp_name = $fileInfo['tmp_name'];
                $originalFileName = $fileInfo['name'];
                $filePath = "uploads/{$originalFileName}";

                // Make filePath safe (i.e. valid path and non-existing)
                $filePath = getSafeFileName($fs, $filePath);
                
                $stream = fopen($tmp_name, 'r+');
                $fs->writeStream($filePath, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                
                // Populate filePath and originalFileName relations in database
                $newResource->link($filePath, 'filePath[FileObject*FilePath]')->add();
                $newResource->link($originalFileName, 'originalFileName[FileObject*FileName]')->add();
            } else {
                // See: https://www.php.net/manual/en/features.file-upload.errors.php
                throw new UploadException($fileInfo['error']);
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
     */
    public function set(mixed $value = null): ?Resource
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
     */
    public static function makeWithoutInterface(Concept $concept): ResourceList
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var

        $one = $ampersandApp->getModel()->getConcept('ONE')->makeAtom('ONE');
        return new ResourceList($one, Ifc::getNullObject($concept, $ampersandApp), '');
    }
}
