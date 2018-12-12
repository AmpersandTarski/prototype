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
        // Checks
        if (!$ifcObj->getTargetConcept()->isObject()) {
            // throw new Exception("Cannot instantiate resource list for non-object concept '{$ifcObj->getTargetConcept()}'", 500);
            throw new Exception("Resource type not found", 404); // HTTP friendly response
        }
        
        $this->srcAtom = $srcAtom;
        $this->ifcObject = $ifcObj;
        $this->pathEntry = $pathEntry;
    }

    public function getPathEntry(): string
    {
        return $this->pathEntry;
    }

    public function getIfcObject(): InterfaceObjectInterface
    {
        return $this->ifcObject;
    }

    public function isUni(): bool
    {
        return $this->ifcObject->isUni();
    }

    public function one(string $tgtId): Resource
    {
        $tgts = $this->ifcObject->getTgtAtoms($this->srcAtom, $tgtId);

        if (!empty($tgts)) {
            // Resource found
            return $this->makeResource(current($tgts));
        } else {
            // When not found
            throw new Exception("Resource '{$tgtId}' not found", 404);
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

    public function get(int $options = Options::DEFAULT_OPTIONS, int $depth = null)
    {
        return $this->ifcObject->read($this->srcAtom, $options, $depth);
    }

    public function post(stdClass $resourceToPost = null): Resource
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        $newResource = $this->makeResource($this->ifcObject->create($this->srcAtom));

        // Special case for file upload
        if ($newResource->concept->isFileObject()) {
            if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                $tmp_name = $_FILES['file']['tmp_name'];
                $originalFileName = $_FILES['file']['name'];

                $appAbsolutePath = $ampersandApp->getSettings()->get('global.absolutePath');
                $uploadFolder = $ampersandApp->getSettings()->get('global.uploadPath');
                $dest = getSafeFileName($appAbsolutePath . DIRECTORY_SEPARATOR . $uploadFolder . DIRECTORY_SEPARATOR . $originalFileName);
                $relativePath = $uploadFolder . '/' . pathinfo($dest, PATHINFO_BASENAME); // use forward slash as this is used on the web
                
                $result = move_uploaded_file($tmp_name, $dest);
                
                if (!$result) {
                    throw new Exception("Error in file upload", 500);
                }
                
                // Populate filePath and originalFileName relations in database
                $newResource->link($relativePath, 'filePath[FileObject*FilePath]')->add();
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

    public function add(Atom $atom): Resource
    {
        // TODO: add check if provided $atom is of same type (or in same classification tree) as target concept of this resourcelist
        $this->ifcObject->add($this->srcAtom, $atom->id);
        return $this->makeResource($atom);
    }

    public function remove(Resource $resource)
    {
        $this->ifcObject->remove($this->srcAtom, $resource->id);
    }

    protected function makeResource(Atom $atom): Resource
    {
        return new Resource($atom->id, $atom->concept, $this);
    }

    /**********************************************************************************************
     * STATIC METHODS
     *********************************************************************************************/

    public static function makeFromInterface(Atom $srcAtom, string $ifcId): ResourceList
    {
        return new ResourceList($srcAtom, Ifc::getInterface($ifcId)->getIfcObject(), '');
    }

    public static function makeWithoutInterface(string $resourceType): ResourceList
    {
        $one = new Atom('ONE', Concept::getConcept('ONE'));
        return new ResourceList($one, InterfaceObjectFactory::getNullObject($resourceType), '');
    }
}
