<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Core\Concept;
use Ampersand\Core\Relation;
use Ampersand\Core\SrcOrTgt;
use Ampersand\Core\TType;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\NotDefined\InterfaceNotDefined;
use Ampersand\Exception\MetaModelException;
use Ampersand\Exception\MethodNotAllowedException;
use Ampersand\Interfacing\AbstractIfcObject;
use Ampersand\Interfacing\BoxHeader;
use Ampersand\Interfacing\Ifc;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\Resource;
use Ampersand\Interfacing\View;
use Ampersand\Plugs\IfcPlugInterface;
use Ampersand\Plugs\MysqlDB\TableType;
use function Ampersand\Misc\isSequential;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceExprObject extends AbstractIfcObject implements InterfaceObjectInterface
{
    /**
     * Dependency injection of an IfcPlug implementation
     */
    protected IfcPlugInterface $plug;
    
    /**
     * Interface id (i.e. safe name) to use in framework
     * TODO: rename property to $name
     */
    protected string $id;
    
    /**
     * Path to this interface object; concatenation of interface name + sub interface labels
     */
    protected string $path;
    
    /**
     * Interface name to show in UI
     */
    protected string $label;
    
    protected bool $crudC;
    protected bool $crudR;
    protected bool $crudU;
    protected bool $crudD;
    
    protected ?Relation $relation;
    protected ?bool $relationIsFlipped;
    
    protected bool $isUni;
    protected bool $isTot;
    protected bool $isIdent;
    
    protected string $query;

    /**
     * Determines if query contains data for subinterfaces
     *
     * See https://github.com/AmpersandTarski/Ampersand/issues/217
     */
    protected bool $queryContainsSubData = false;
    
    protected Concept $srcConcept;
    protected Concept $tgtConcept;
    
    protected ?View $view;
    protected ?BoxHeader $boxHeader = null;
    
    /**
     * @var \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    protected array $subInterfaces = [];

    /**
     * Interface of which this object is part of
     */
    protected Ifc $rootIfc;

    /**
     * Constructor
     */
    public function __construct(array $ifcDef, IfcPlugInterface $plug, Ifc $rootIfc, ?InterfaceObjectInterface $parent = null)
    {
        if ($ifcDef['type'] != 'ObjExpression') {
            throw new FatalException("Provided interface definition is not of type ObjExpression");
        }

        $this->plug = $plug;
        $this->rootIfc = $rootIfc;
        
        // Set attributes from $ifcDef
        $this->id = $ifcDef['name'];
        $this->label = $ifcDef['label'];
        $this->view = is_null($ifcDef['viewName']) ? null : $rootIfc->getModel()->getView($ifcDef['viewName']);
        $this->path = is_null($parent) ? $this->label : "{$parent->getPath()}/{$this->label}"; // Use label, because path is only used for human readable purposes (e.g. Exception messages)
        
        // Information about the (editable) relation if applicable
        $this->relation = is_null($ifcDef['relation']) ? null : $rootIfc->getModel()->getRelation($ifcDef['relation']);
        $this->relationIsFlipped = $ifcDef['relationIsFlipped'];
        
        // Interface expression information
        if (!isset($ifcDef['expr'])) {
            throw new FatalException("Expression information not defined for interface object {$this->path}");
        }
        $this->srcConcept = $this->rootIfc->getModel()->getConcept($ifcDef['expr']['srcConceptName']);
        $this->tgtConcept = $this->rootIfc->getModel()->getConcept($ifcDef['expr']['tgtConceptName']);
        $this->isUni = $ifcDef['expr']['isUni'];
        $this->isTot = $ifcDef['expr']['isTot'];
        $this->isIdent = $ifcDef['expr']['isIdent'];
        $this->query = $ifcDef['expr']['query'];
        $this->queryContainsSubData = strpos($this->query, 'ifc_') !== false;
        
        // Subinterfacing
        if (isset($ifcDef['subinterfaces'])) {
            $subIfcsDef = $ifcDef['subinterfaces'];

            // Subinterfacing is not supported/possible for tgt concepts with a scalar representation type (i.e. non-objects)
            if (!$this->tgtConcept->isObject()) {
                throw new MetaModelException("Subinterfacing is not supported for concepts with a scalar representation type (i.e. non-objects). (Sub)Interface '{$this->path}' with target {$this->tgtConcept} (ttype:{$this->tgtConcept->type->value}) has subinterfaces specified");
            }

            // Process boxheader information
            if (isset($subIfcsDef['boxHeader'])) {
                $this->boxHeader = new BoxHeader($subIfcsDef['boxHeader']);
            }
            
            // Inline subinterface definitions
            foreach ((array) $subIfcsDef['ifcObjects'] as $subIfcDef) {
                $subifc = $rootIfc->newObject($subIfcDef, $this->plug, $this);
                $this->subInterfaces[$subifc->getIfcId()] = $subifc;
            }
        }
        
        // CRUD rights
        if (!isset($ifcDef['crud'])) {
            throw new FatalException("Cannot determine crud rights for interface object {$this->path}");
        }
        $this->crudC = $ifcDef['crud']['create'];
        $this->crudR = $ifcDef['crud']['read'];
        $this->crudU = $ifcDef['crud']['update'];
        $this->crudD = $ifcDef['crud']['delete'];
    }
    
    /**
     * Function is called when object is treated as a string
     */
    public function __toString(): string
    {
        return $this->id;
    }

    public function getIfcId(): string
    {
        return $this->id;
    }

    public function getIfcLabel(): string
    {
        return $this->label;
    }

    public function getSrcConcept(): Concept
    {
        return $this->srcConcept;
    }

    public function getTgtConcept(): Concept
    {
        return $this->tgtConcept;
    }
    
    /**
     * Returns interface relation (when interface expression = relation), throws exception otherwise
     */
    protected function relation(): Relation
    {
        if (is_null($this->relation)) {
            throw new BadRequestException("Interface expression for '{$this->label}' is not an (editable) relation");
        } else {
            return $this->relation;
        }
    }
    
    /**
     * Returns if interface expression is editable (i.e. expression = relation)
     */
    protected function isEditable(): bool
    {
        return !is_null($this->relation);
    }
    
    /**
     * Array with all editable concepts for this interface and all sub interfaces
     * @return \Ampersand\Core\Concept[]
     */
    public function getEditableConcepts(): array
    {
        $arr = [];
        
        // Determine editable concept for this interface
        if ($this->crudU() && $this->tgtConcept->isObject()) {
            $arr[] = $this->tgtConcept;
        }
        
        // Add editable concepts for subinterfaces
        foreach ($this->getSubinterfaces(Options::DEFAULT_OPTIONS | Options::INCLUDE_REF_IFCS) as $ifc) {
            $arr = array_merge($arr, $ifc->getEditableConcepts());
        }
        
        return $arr;
    }

    /**
     * Returns if interface expression relation is a property
     */
    protected function isProp(): bool
    {
        return is_null($this->relation) ? false : ($this->relation->isProp && !$this->isIdent());
    }

    /**
     * Returns if interface object is a leaf node
     */
    protected function isLeaf(int $options = Options::DEFAULT_OPTIONS): bool
    {
        return empty($this->getSubinterfaces($options));
    }

    protected function isBox(): bool
    {
        return isset($this->boxHeader);
    }
    
    /**
     * Returns if the interface expression isIdent
     *
     * Note! Epsilons are not included
     */
    public function isIdent(): bool
    {
        return $this->isIdent;
    }
    
    public function isUni(): bool
    {
        return $this->isUni;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function crudC(): bool
    {
        return $this->crudC;
    }
    
    public function crudR(): bool
    {
        return $this->crudR;
    }
    
    public function crudU(): bool
    {
        return $this->crudU;
    }
    
    public function crudD(): bool
    {
        return $this->crudD;
    }

    /**
     * Returns generated query for this interface expression
     */
    public function getQuery(): string
    {
        return str_replace('_SESSION', session_id(), $this->query); // Replace _SESSION var with current session id.
    }

    /**
     * Function to manually set optimized query
     */
    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    /**
     * Returns if subinterface is defined
     */
    public function hasSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): bool
    {
        return array_key_exists($ifcId, $this->getSubinterfaces($options));
    }
    
    public function getSubinterface(string $ifcId, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
    {
        if (!array_key_exists($ifcId, $subifcs = $this->getSubinterfaces($options))) {
            throw new InterfaceNotDefined("Subinterface '{$ifcId}' does not exist in interface '{$this->path}'");
        }
    
        return $subifcs[$ifcId];
    }
    
    public function getSubinterfaceByLabel(string $ifcLabel, int $options = Options::DEFAULT_OPTIONS): InterfaceObjectInterface
    {
        foreach ($this->getSubinterfaces($options) as $ifc) {
            if ($ifc->getIfcLabel() == $ifcLabel) {
                return $ifc;
            }
        }
        
        throw new InterfaceNotDefined("Subinterface '{$ifcLabel}' does not exist in interface '{$this->path}'");
    }
    
    /**
     * Undocumented function
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS): array
    {
        return $this->subInterfaces;
    }
    
    /**
     * @return \Ampersand\Interfacing\Ifc[]
     */
    protected function getNavInterfacesForTgt(): array
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        
        return array_filter(
            $ampersandApp->getInterfacesToReadConcept($this->tgtConcept),
            function (Ifc $ifc) {
                return !$ifc->isAPI();
            }
        );
    }

    /**
     * Get view data for specified atom
     */
    public function getViewData(Atom $tgtAtom): array
    {
        if (is_null($this->view)) {
            return $this->tgtConcept->getViewData($tgtAtom);
        } else {
            return $this->view->getViewData($tgtAtom);
        }
    }

    /**
     * Returns path for given tgt atom
     */
    public function buildResourcePath(Atom $tgt, string $pathToSrc): string
    {
        /* Skip resource id for ident interface expressions (I[Concept])
        * I expressions are commonly used for adding structure to an interface using (sub) boxes
        * This results in verbose paths
        * e.g.: pathToApi/resource/Person/John/PersonIfc/John/PersonDetails/John/Name
        * By skipping ident expressions the paths are more concise without loosing information
        * e.g.: pathToApi/resource/Person/John/PersonIfc/PersonDetails/Name
        */
        if ($this->isIdent()) {
            return $pathToSrc . '/' . $this->getIfcId();
        } else {
            return $pathToSrc . '/' . $this->getIfcId() . '/' . $tgt->getId();
        }
    }
    
    public function read(
        Atom $src,
        string $pathToSrc,
        ?string $tgtId = null,
        int $options = Options::DEFAULT_OPTIONS,
        ?int $depth = null,
        array $recursionArr = []
    ): mixed
    {
        if (!$this->crudR()) {
            throw new MethodNotAllowedException("Read not allowed for ". $this->getPath());
        }

        // Initialize result
        $result = [];

        // Object nodes
        if ($this->tgtConcept->isObject()) {
            foreach ($this->getTgtAtoms($src, $tgtId) as $tgt) {
                $result[] = $this->getResourceContent($tgt, $pathToSrc, $options, $depth, $recursionArr);
            }
            
            // Special case for leave PROP: return false when result is empty, otherwise true (i.e. I atom must be present)
            // Enables boolean functionality for editing ampersand property relations
            if ($this->isLeaf($options) && $this->isProp()) {
                if (empty($result)) {
                    return false;
                } else {
                    return true;
                }
            }
            
        // Non-object nodes (i.e. leaves, because subinterfaces are not allowed for non-objects)
        // Notice that ->getResourceContent() is not called. The interface stops here.
        } else {
            // Temporary hack to prevent passwords from being returned to user
            if ($this->tgtConcept->type === TType::PASSWORD) {
                $result = [];
            } else {
                $result = array_map(function (Atom $tgt) {
                    return $tgt->jsonSerialize();
                }, $this->getTgtAtoms($src, $tgtId));
            }
        }

        // Return result
        if ($this->isUni || isset($tgtId)) { // single object
            return empty($result) ? null : current($result);
        } else { // array
            return $result;
        }
    }

    protected function getResourceContent(Atom $tgt, string $pathToSrc, $options, $depth, $recursionArr): string|array
    {
        $tgtPath = $this->buildResourcePath($tgt, $pathToSrc);
        
        // Init content array
        $content = [];

        // Basic UI data of a resource
        if ($options & Options::INCLUDE_UI_DATA) {
            $viewData = $this->getViewData($tgt);

            // Add Ampersand atom attributes
            $content['_id_'] = $tgt->getId();
            $content['_label_'] = empty($viewData) ? $tgt->getLabel() : implode('', $viewData);
            $content['_path_'] = $tgtPath;
            
            // Add view data if array is assoc (i.e. not sequential, because then it is a label)
            if (!isSequential($viewData)) {
                $content['_view_'] = $viewData;
            }
        // Not INCLUDE_UI_DATA and ifc isLeaf (i.e. there are no subinterfaces) -> directly return $tgt identifier
        } elseif ($this->isLeaf($options)) {
            return $tgt->getId();
        }

        // Determine if sorting values must be added
        $addSortValues = $this->isBox() && $this->boxHeader->isSortable() && ($options & Options::INCLUDE_SORT_DATA);

        // Get data of subinterfaces if depth is not provided or max depth not yet reached
        if (is_null($depth) || $depth > 0) {
            if (!is_null($depth)) {
                $depth--; // decrease depth by 1
            }

            foreach ($this->getSubinterfaces($options) as $ifcObj) {
                if (!$ifcObj->crudR()) {
                    continue; // skip subinterface if not given read rights (otherwise exception will be thrown when getting content)
                }
                $content[$ifcObj->getIfcId()] = $value = $ifcObj->read($tgt, $tgtPath, null, $options, $depth, $recursionArr);

                // Add sort values
                if ($ifcObj->isUni() && $addSortValues) {
                    switch (gettype($value)) {
                        case 'object': // an object of Atom class
                            /** @var \Ampersand\Core\Atom $value */
                            $sortValue = $value->jsonSerialize();
                            break;
                        case 'array': // content of Resource
                            $sortValue = $value['_label_'] ?? null;
                            break;
                        case 'NULL':
                            $sortValue = null;
                            break;
                        case 'unknown type':
                        case 'resource (closed)':
                        case 'resource':
                            throw new FatalException("Unexpected error. Not implemented case for sortvalue");
                            break;
                        default:
                            $sortValue = $value;
                            break;
                    }
                    $content['_sortValues_'][$ifcObj->getIfcId()] = $sortValue;
                }
            }
        }

        // Interface(s) to navigate to for this resource
        if ($options & Options::INCLUDE_NAV_IFCS) {
            $content['_ifcs_'] = array_values(
                array_map(function (Ifc $o) {
                    return ['id' => $o->getId(), 'label' => $o->getLabel()];
                }, $this->getNavInterfacesForTgt())
            );
        }

        return $content;
    }

    public function create(Atom $src, $tgtId = null): Atom
    {
        // If expression is ident, create is not needed, return src atom immediately
        if ($this->isIdent() && $src->exists()) {
            return $src;
        }

        if (!$this->crudC()) {
            throw new MethodNotAllowedException("Create not allowed for ". $this->getPath());
        }
        
        // Make new resource
        if (isset($tgtId)) {
            $tgtAtom = new Atom($tgtId, $this->tgtConcept);
            if ($tgtAtom->exists()) {
                throw new BadRequestException("Cannot create resource that already exists");
            }
        } else {
            $tgtAtom = $this->tgtConcept->createNewAtom();
        }

        // Add to plug (e.g. database)
        $tgtAtom->add();
        
        // If interface is editable, also add tuple(src, tgt) in interface relation
        if ($this->isEditable()) {
            $this->add($src, $tgtAtom->getId(), true); // skip crud check because adding is implictly allowed for a create
        }

        return $tgtAtom;
    }

    /**
     * Set provided value (for univalent interfaces)
     */
    public function set(Atom $src, mixed $value = null): ?Atom
    {
        if (!$this->isUni()) {
            throw new BadRequestException("Cannot use set() for non-univalent interface " . $this->getPath() . ". Use add or remove instead");
        }

        if (is_array($value)) {
            throw new BadRequestException("Non-array expected but array provided while updating " . $this->getPath());
        }
        
        // Handle Ampersand properties [PROP]
        if ($this->isProp()) {
            if ($value === true) {
                return $this->add($src, $src->getId());
            } elseif ($value === false) {
                $this->remove($src, $src->getId());
                return null;
            } else {
                throw new BadRequestException("Boolean expected, non-boolean provided.");
            }
        } elseif ($this->isIdent()) { // Ident object => no need to set
            return $src;
        } else {
            if (is_null($value)) {
                $this->removeAll($src);
                return null;
            } else {
                return $this->add($src, $value);
            }
        }
    }

    /**
     * Add value to resource list
     */
    public function add(Atom $src, mixed $value, bool $skipCrudUCheck = false): Atom
    {
        if (!isset($value)) {
            throw new BadRequestException("Cannot add item. Value not provided");
        }
        if (is_object($value) || is_array($value)) {
            throw new BadRequestException("Literal expected but " . gettype($value) . " provided while updating " . $this->getPath());
        }
        
        if (!$this->isEditable()) {
            throw new MethodNotAllowedException("Interface is not editable " . $this->getPath());
        }
        if (!$this->crudU() && !$skipCrudUCheck) {
            throw new MethodNotAllowedException("Update not allowed for " . $this->getPath());
        }
        
        $tgt = new Atom($value, $this->tgtConcept);
        if ($tgt->concept->isObject() && !$this->crudC() && !$tgt->exists()) {
            throw new MethodNotAllowedException("Create not allowed for " . $this->getPath());
        }
        
        $tgt->add();
        $src->link($tgt, $this->relation(), $this->relationIsFlipped ?? false)->add();
        
        return $tgt;
    }

    /**
     * Remove value from resource list
     */
    public function remove(Atom $src, mixed $value): void
    {
        if (!isset($value)) {
            throw new BadRequestException("Cannot remove item. Value not provided");
        }
        if (is_object($value) || is_array($value)) {
            throw new BadRequestException("Literal expected but " . gettype($value) . " provided while updating " . $this->getPath());
        }
        
        if (!$this->isEditable()) {
            throw new MethodNotAllowedException("Interface is not editable " . $this->getPath());
        }
        if (!$this->crudU()) {
            throw new MethodNotAllowedException("Update not allowed for " . $this->getPath());
        }
        
        $tgt = new Atom($value, $this->tgtConcept);
        $src->link($tgt, $this->relation(), $this->relationIsFlipped ?? false)->delete();
        
        return;
    }

    /**
     * Undocumented function
     */
    public function removeAll(Atom $src): void
    {
        if (!$this->isEditable()) {
            throw new MethodNotAllowedException("Interface is not editable " . $this->getPath());
        }
        if (!$this->crudU()) {
            throw new MethodNotAllowedException("Update not allowed for " . $this->getPath());
        }
        
        $this->relation->deleteAllLinks($src, ($this->relationIsFlipped ? SrcOrTgt::TGT : SrcOrTgt::SRC));

        return;
    }

    public function delete(Resource $tgtAtom): void
    {
        if (!$this->crudD()) {
            throw new MethodNotAllowedException("Delete not allowed for ". $this->getPath());
        }
        
        // Perform delete
        $tgtAtom->concept->deleteAtom($tgtAtom);

        return;
    }

    /**
     * Return list of target atoms
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getTgtAtoms(Atom $src, ?string $selectTgt = null): array
    {
        if (!$this->crudR()) {
            throw new MethodNotAllowedException("Read not allowed for " . $this->getPath());
        }

        $tgts = [];

        // If interface isIdent (i.e. expr = I[Concept]), and no epsilon is required (i.e. srcConcept equals tgtConcept of parent ifc) we can return the src
        // However, if query for this expression contains sub data, it is more efficient to evaluate the query (see Issue #217)
        if ($this->isIdent() && $this->srcConcept === $src->concept && !$this->queryContainsSubData) {
            $tgts[] = $src;
        } else {
            // Try to get tgt atom from src query data (in case of uni relation in same table)
            $tgtId = $src->getQueryData('ifc_' . $this->id, $exists); // column is prefixed with ifc_ in query data
            if ($exists && !$this->queryContainsSubData) {
                if (!is_null($tgtId)) {
                    $tgts[] = new Atom($tgtId, $this->tgtConcept);
                }
            // Evaluate interface expression
            } else {
                foreach ((array) $this->plug->executeIfcExpression($this, $src) as $row) {
                    $tgtAtom = new Atom($row['tgt'], $this->tgtConcept);
                    $tgtAtom->setQueryData($row);
                    $tgts[] = $tgtAtom;
                }
            }
        }

        // Integrity check
        if ($this->isUni() && count($tgts) > 1) {
            throw new FatalException("Univalent (sub)interface returns more than 1 resource: " . $this->getPath());
        }

        // If specific target is specified, pick that one out
        if (!is_null($selectTgt)) {
            return array_filter($tgts, function (Atom $item) use ($selectTgt) {
                return $item->getId() === $selectTgt;
            });
        }
        
        return $tgts;
    }

    public function getTechDetails(): array
    {
        return
            [ 'path' => $this->getPath()
            , 'label' => $this->getIfcLabel()
            , 'crudR' => $this->crudR()
            , 'crudU' => $this->crudU()
            , 'crudD' => $this->crudD()
            , 'crudC' => $this->crudC()
            , 'src' => $this->srcConcept->name
            , 'tgt' => $this->tgtConcept->name
            , 'view' => $this->view->label ?? ''
            , 'relation' => $this->relation?->signature
            , 'flipped' => $this->relationIsFlipped
            , 'ref' => null
            ];
    }

    public function diagnostics(): array
    {
        $diagnostics = [];

        if ($this->crudU() && !$this->isEditable()) {
            $diagnostics[] = [ 'interface' => $this->getPath()
                             , 'message' => "Update rights (crUd) specified while interface expression is not an editable relation!"
                             ];
        }

        if ($this->crudC() && !$this->tgtConcept->isObject()) {
            $diagnostics[] = [ 'interface' => $this->getPath()
                             , 'message' => "Create rights (Crud) specified while target concept is a scalar. This has no affect!"
                             ];
        }

        if ($this->crudD() && !$this->tgtConcept->isObject()) {
            $diagnostics[] = [ 'interface' => $this->getPath()
                             , 'message' => "Delete rights (cruD) specified while target concept is a scalar. This has no affect!"
                             ];
        }

        if (!$this->crudR()) {
            $diagnostics[] = [ 'interface' => $this->getPath()
                             , 'message' => "No read rights specified. Are you sure?"
                             ];
        }

        // Check for unsupported patchReplace functionality due to missing 'old value'. Related with issue #318. TODO: still needed??
        if ($this->isEditable() && $this->crudU() && !$this->tgtConcept->isObject() && $this->isUni()) {
            // Only applies to editable relations
            // Only applies to crudU, because issue is with patchReplace, not with add/remove
            // Only applies to scalar, because objects don't use patchReplace, but Remove and Add
            // Only if interface expression (not! the relation) is univalent, because else a add/remove option is used in the UI
            if ((!$this->relationIsFlipped && $this->relation()->getMysqlTable()->inTableOf() === TableType::Tgt)
                    || ($this->relationIsFlipped && $this->relation()->getMysqlTable()->inTableOf() === TableType::Src)) {
                $diagnostics[] = [ 'interface' => $this->getPath()
                                 , 'message' => "Unsupported edit functionality due to combination of factors. See issue #318"
                                 ];
            }
        }

        return $diagnostics;
    }
}
