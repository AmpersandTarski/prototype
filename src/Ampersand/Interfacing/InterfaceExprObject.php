<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Exception;
use Ampersand\Core\Relation;
use Ampersand\Core\Concept;
use Ampersand\Interfacing\View;
use Ampersand\Core\Atom;
use Ampersand\Misc\Config;
use function Ampersand\Misc\isSequential;
use Ampersand\Plugs\IfcPlugInterface;
use Ampersand\Interfacing\Options;
use Ampersand\Model\InterfaceObjectFactory;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Interfacing\Resource;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceExprObject implements InterfaceObjectInterface
{
    /**
     * Dependency injection of an IfcPlug implementation
     * @var \Ampersand\Plugs\IfcPlugInterface
     */
    protected $plug;
    
    /**
     * Interface id (i.e. safe name) to use in framework
     * @var string
     */
    protected $id;
    
    /**
     *
     * @var string
     */
    protected $path;
    
    /**
     * Interface name to show in UI
     * @var string
     */
    protected $label;
    
    /**
     * Specifies if this interface object is a toplevel interface (true) or subinterface (false)
     * @var boolean
     */
    protected $isRoot;
    
    /**
     * Roles that have access to this interface
     * Only applies to top level interface objects
     * @var string[]
     */
    public $ifcRoleNames = [];
    
    /**
     *
     * @var boolean
     */
    private $crudC;
    
    /**
     *
     * @var boolean
     */
    private $crudR;
    
    /**
     *
     * @var boolean
     */
    private $crudU;
    
    /**
     *
     * @var boolean
     */
    private $crudD;
    
    /**
     *
     * @var \Ampersand\Core\Relation|null
     */
    private $relation;
    
    /**
     *
     * @var boolean|null
     */
    public $relationIsFlipped;
    
    /**
     *
     * @var boolean
     */
    private $isUni;
    
    /**
     *
     * @var boolean
     */
    private $isTot;
    
    /**
     *
     * @var boolean
     */
    private $isIdent;
    
    /**
     *
     * @var string
     */
    private $query;
    
    /**
     *
     * @var \Ampersand\Core\Concept
     */
    public $srcConcept;
    
    /**
     *
     * @var \Ampersand\Core\Concept
     */
    public $tgtConcept;
    
    /**
     *
     * @var \Ampersand\Interfacing\View|null
     */
    private $view;

    /**
     * Specifies the class of the BOX (in case of BOX interface)
     * e.g. in ADL script: INTERFACE "test" : expr BOX <SCOLS> []
     * the boxClass is 'SCOLS'
     * @var string
     */
    protected $boxClass = null;
    
    /**
     *
     * @var string
     */
    private $refInterfaceId;
    
    /**
     *
     * @var boolean
     */
    private $isLinkTo = false;
    
    /**
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    private $subInterfaces = [];

    /**
     * Parent interface (when not root interface)
     *
     * @var \Ampersand\Interfacing\InterfaceObjectInterface
     */
    protected $parentIfc = null;

    /**
     * Constructor
     *
     * @param array $ifcDef Interface object definition as provided by Ampersand generator
     * @param \Ampersand\Plugs\IfcPlugInterface $plug
     * @param string|null $pathEntry
     * @param bool $rootIfc Specifies if this interface object is a toplevel interface (true) or subinterface (false)
     */
    public function __construct(array $ifcDef, IfcPlugInterface $plug, string $pathEntry = null, bool $rootIfc = false)
    {
        if ($ifcDef['type'] != 'ObjExpression') {
            throw new Exception("Provided interface definition is not of type ObjExpression", 500);
        }

        $this->plug = $plug;
        $this->isRoot = $rootIfc;
        
        // Set attributes from $ifcDef
        $this->id = $ifcDef['id'];
        $this->label = $ifcDef['label'];
        $this->view = is_null($ifcDef['viewId']) ? null : View::getView($ifcDef['viewId']);
        
        $this->path = is_null($pathEntry) ? $this->label : "{$pathEntry}/{$this->label}"; // Use label, because path is only used for human readable purposes (e.g. Exception messages)
        
        // Information about the (editable) relation if applicable
        $this->relation = is_null($ifcDef['relation']) ? null : Relation::getRelation($ifcDef['relation']);
        $this->relationIsFlipped = $ifcDef['relationIsFlipped'];
        
        // Interface expression information
        $this->srcConcept = Concept::getConcept($ifcDef['expr']['srcConceptId']);
        $this->tgtConcept = Concept::getConcept($ifcDef['expr']['tgtConceptId']);
        $this->isUni = $ifcDef['expr']['isUni'];
        $this->isTot = $ifcDef['expr']['isTot'];
        $this->isIdent = $ifcDef['expr']['isIdent'];
        $this->query = $ifcDef['expr']['query'];
        
        // Subinterfacing
        if (!is_null($ifcDef['subinterfaces'])) {
            // Subinterfacing is not supported/possible for tgt concepts with a scalar representation type (i.e. non-objects)
            if (!$this->tgtConcept->isObject()) {
                throw new Exception("Subinterfacing is not supported for concepts with a scalar representation type (i.e. non-objects). (Sub)Interface '{$this->path}' with target {$this->tgtConcept} (type:{$this->tgtConcept->type}) has subinterfaces specified", 501);
            }
            
            /* Reference to top level interface
             * e.g.:
             * INTERFACE "A" : expr1 INTERFACE "B"
             * INTERFACE "B" : expr2 BOX ["label" : expr3]
             *
             * is interpreted as:
             * INTERFACE "A" : expr1;epxr2 BOX ["label" : expr3]
             */
            $this->refInterfaceId = $ifcDef['subinterfaces']['refSubInterfaceId'];
            $this->isLinkTo = $ifcDef['subinterfaces']['refIsLinkTo'];
            $this->boxClass = $ifcDef['subinterfaces']['boxClass'];
            
            // Inline subinterface definitions
            foreach ((array)$ifcDef['subinterfaces']['ifcObjects'] as $subIfcDef) {
                $ifc = $subIfcDef['type'] == 'ObjText' ? new InterfaceTxtObject($subIfcDef, $this->plug, $this->path) : new InterfaceExprObject($subIfcDef, $this->plug, $this->path);
                $ifc->parentIfc = $this;
                $this->subInterfaces[$ifc->id] = $ifc;
            }
        }
        
        // CRUD rights
        $this->crudC = $this->isRef() ? null : $ifcDef['crud']['create'];
        $this->crudR = $this->isRef() ? null : $ifcDef['crud']['read'];
        $this->crudU = $this->isRef() ? null : $ifcDef['crud']['update'];
        $this->crudD = $this->isRef() ? null : $ifcDef['crud']['delete'];
    }
    
    /**
     * Function is called when object is treated as a string
     * @return string
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
    
    /**
     * Returns interface relation (when interface expression = relation), throws exception otherwise
     * @throws \Exception when interface expression is not an (editable) relation
     * @return \Ampersand\Core\Relation
     */
    public function relation(): Relation
    {
        if (is_null($this->relation)) {
            throw new Exception("Interface expression for '{$this->label}' is not an (editable) relation", 500);
        } else {
            return $this->relation;
        }
    }
    
    /**
     * Returns if interface expression is editable (i.e. expression = relation)
     * @return bool
     */
    public function isEditable(): bool
    {
        return !is_null($this->relation);
    }
    
    /**
     * Array with all editable concepts for this interface and all sub interfaces
     * @var Concept[]
     */
    public function getEditableConcepts()
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
     * @return bool
     */
    public function isProp(): bool
    {
        return is_null($this->relation) ? false : ($this->relation->isProp && !$this->isIdent());
    }
    
    /**
     * Returns if interface is a reference to another interface
     * @return bool
     */
    protected function isRef(): bool
    {
        return !is_null($this->refInterfaceId);
    }
    
    /**
     * Returns referenced interface object
     * @throws Exception when $this is not a reference interface
     * @return InterfaceObjectInterface
     */
    public function getRefToIfc()
    {
        if ($this->isRef()) {
            return InterfaceObjectFactory::getInterface($this->refInterfaceId);
        } else {
            throw new Exception("Interface is not a reference interface: " . $this->getPath(), 500);
        }
    }
    
    /**
     * Returns if interface object is a top level interface
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->isRoot;
    }
    
    /**
     * Returns if interface object is a leaf node
     * @return bool
     */
    public function isLeaf(): bool
    {
        return empty($this->getSubinterfaces());
    }
    
    /**
     * Returns if interface is a public interface (i.e. accessible every role, incl. no role)
     * @return bool
     */
    public function isPublic(): bool
    {
        return empty($this->ifcRoleNames) && $this->isRoot();
    }
    
    /**
     * Returns if the interface expression isIdent
     * Note! Epsilons are not included
     *
     * @return boolean
     */
    public function isIdent(): bool
    {
        return $this->isIdent;
    }
    
    public function isUni(): bool
    {
        return $this->isUni;
    }
    
    public function isTot(): bool
    {
        return $this->isTot;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function crudC(): bool
    {
        // If crudC not specified during object construction (e.g. in case of ref interface)
        if (is_null($this->crudC)) {
            if ($this->isRef()) {
                $this->crudC = $this->getRefToIfc()->crudC();
            } else {
                throw new Exception("Create rights not specified for interface " . $this->getPath(), 500);
            }
        }
        
        return $this->crudC;
    }
    
    public function crudR(): bool
    {
        // If crudR not specified during object construction (e.g. in case of ref interface)
        if (is_null($this->crudR)) {
            if ($this->isRef()) {
                $this->crudR = $this->getRefToIfc()->crudR();
            } else {
                throw new Exception("Read rights not specified for interface " . $this->getPath(), 500);
            }
        }
        
        return $this->crudR;
    }
    
    public function crudU(): bool
    {
        // If crudU not specified during object construction (e.g. in case of ref interface)
        if (is_null($this->crudU)) {
            if ($this->isRef()) {
                $this->crudU = $this->getRefToIfc()->crudU();
            } else {
                throw new Exception("Update rights not specified for interface " . $this->getPath(), 500);
            }
        }
        
        return $this->crudU;
    }
    
    public function crudD(): bool
    {
        // If crudD not specified during object construction (e.g. in case of ref interface)
        if (is_null($this->crudD)) {
            if ($this->isRef()) {
                $this->crudD = $this->getRefToIfc()->crudD();
            } else {
                throw new Exception("Delete rights not specified for interface " . $this->getPath(), 500);
            }
        }
        
        return $this->crudD;
    }

    /**
     * Returns generated query for this interface expression
     * @return string
     */
    public function getQuery(): string
    {
        return str_replace('_SESSION', session_id(), $this->query); // Replace _SESSION var with current session id.
    }

    /**
     * Returns parent interface object (or null if not applicable)
     *
     * @return \Ampersand\Interfacing\InterfaceObjectInterface|null
     */
    public function getParentInterface()
    {
        return $this->parentIfc;
    }
    
    /**
     * @param string $ifcId
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public function getSubinterface(string $ifcId): InterfaceObjectInterface
    {
        if (!array_key_exists($ifcId, $subifcs = $this->getSubinterfaces())) {
            throw new Exception("Subinterface '{$ifcId}' does not exist in interface '{$this->path}'", 500);
        }
    
        return $subifcs[$ifcId];
    }
    
    /**
     * @param string $ifcLabel
     * @return \Ampersand\Interfacing\InterfaceObjectInterface
     */
    public function getSubinterfaceByLabel(string $ifcLabel): InterfaceObjectInterface
    {
        foreach ($this->getSubinterfaces() as $ifc) {
            if ($ifc->label == $ifcLabel) {
                return $ifc;
            }
        }
        
        throw new Exception("Subinterface '{$ifcLabel}' does not exist in interface '{$this->path}'", 500);
    }
    
    /**
     * Return array with all sub interface recursively (incl. the interface itself)
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getInterfaceFlattened()
    {
        $arr = [$this];
        foreach ($this->getSubinterfaces(Options::DEFAULT_OPTIONS & ~Options::INCLUDE_REF_IFCS) as $ifc) {
            $arr = array_merge($arr, $ifc->getInterfaceFlattened());
        }
        return $arr;
    }
    
    /**
     * @param int $options
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    protected function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS)
    {
        if ($this->isRef() && ($options & Options::INCLUDE_REF_IFCS) // if ifc is reference to other root ifc, option to include refs must be set (= default)
            && (!$this->isLinkTo || ($options & Options::INCLUDE_LINKTO_IFCS))) { // this ref ifc must not be a LINKTO ór option is set to explicitly include linkto ifcs
        /* Return the subinterfaces of the reference interface. This skips the referenced toplevel interface.
             * e.g.:
             * INTERFACE "A" : expr1 INTERFACE "B"
             * INTERFACE "B" : expr2 BOX ["label" : expr3]
             *
             * is interpreted as:
             * INTERFACE "A" : expr1;epxr2 BOX ["label" : expr3]
             */
            return $this->getRefToIfc()->getSubinterfaces($options);
        } else {
            return $this->subInterfaces;
        }
    }
    
    /**
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     * TODO: move this code, or at least remove dependency to global $container var
     */
    protected function getNavInterfacesForTgt()
    {
        /** @var \Pimple\Container $container */
        global $container;
        $ifcs = [];
        if ($this->isLinkTo && $container['ampersand_app']->isAccessibleIfc($refIfc = $this->getRefToIfc())) {
            $ifcs[] = $refIfc;
        } else {
            $ifcs = $container['ampersand_app']->getInterfacesToReadConcepts([$this->tgtConcept]);
        }
        
        return $ifcs;
    }

    /**
     * Undocumented function
     *
     * @param \Ampersand\Core\Atom $tgtAtom the atom for which to get view data
     * @return array
     */
    public function getViewData(Atom $tgtAtom): array
    {
        if (is_null($this->view)) {
            return $this->tgtConcept->getViewData($tgtAtom);
        } else {
            return $this->view->getViewData($tgtAtom);
        }
    }

    public function all(Resource $src): array
    {
        if (!$this->crudR()) {
            throw new Exception("Read not allowed for " . $this->getPath(), 405);
        }
        
        return $this->getTgtResources($src, true);
    }

    public function one(Resource $src, string $tgtId = null): Resource
    {
        if (!$this->crudR()) {
            throw new Exception("Read not allowed for " . $this->getPath(), 405);
        }

        // If no tgtId is provided, the srcId is used. Usefull for ident interface expressions (I[Concept])
        if (is_null($tgtId)) {
            $tgtId = $src->id;
        }
        
        $tgts = $this->getTgtResources($src, true, $tgtId);

        if (!empty($tgts)) {
            // Resource found
            return current($tgts);
        } elseif ($this->tgtConcept->isObject() && $this->crudC()) {
            // Create the target if allowed
            return $this->makeResource($tgtId, $src);
        } else {
            // When not found
            throw new Exception("Resource not found", 404);
        }
    }

    public function get(Resource $src, Resource $tgt = null, int $options = Options::DEFAULT_OPTIONS, int $depth = null, array $recursionArr = [])
    {
        if (!$this->crudR()) {
            throw new Exception("Read not allowed for ". $this->getPath(), 405);
        }

        // The following check is needed because the frontend UI does not support root interfaces expressions with non-object target concepts (yet)
        // Subinterfaces are not a problem
        if ($this->isRoot() && !$this->tgtConcept->isObject()) {
            throw new Exception("No support for root interface expressions with non-object target concept (see #745)", 501);
        }

        // Initialize result
        $result = [];

        // Object nodes
        if ($this->tgtConcept->isObject()) {
            foreach ($this->getTgtResources($src, true, $tgt->id) as $resource) {
                $result[] = $this->getResourceContent($resource, $options, $depth, $recursionArr);
            }
            
            // Special case for leave PROP: return false when result is empty, otherwise true (i.e. I atom must be present)
            // Enables boolean functionality for editing ampersand property relations
            if ($this->isLeaf() && $this->isProp()) {
                if (empty($result)) {
                    return false;
                } else {
                    return true;
                }
            }
            
        // Non-object nodes (i.e. leaves, because subinterfaces are not allowed for non-objects)
        // Notice that ->getResourceContent() is not called on $resource. The interface stops here.
        } else {
            $result = $this->getTgtAtoms($src); // for json_encode $resource->jsonSerializable() is called
        }

        // Return result
        if ($this->isUni || !is_null($tgt)) { // single object
            return empty($result) ? null : current($result);
        } else { // array
            return $result;
        }
    }

    protected function getResourceContent(Resource $resource, $options, $depth, $recursionArr)
    {
        $content = [];

        // User interface data (_id_, _label_ and _view_ and _path_)
        if ($options & Options::INCLUDE_UI_DATA) {
            // Add Ampersand atom attributes
            $content['_id_'] = $resource->id;
            $content['_label_'] = $resource->getLabel();
            $content['_path_'] = $resource->getPath();
        
            // Add view data if array is assoc (i.e. not sequential)
            $data = $resource->getView();
            if (!isSequential($data)) {
                $content['_view_'] = $data;
            }
        // When no INCLUDE_UI_DATA and no subintefaces -> directly return resource id
        } elseif ($this->isLeaf()) {
            return $resource->id;
        }
        
        // Interface(s) to navigate to for this resource
        if (($options & Options::INCLUDE_NAV_IFCS)) {
            $content['_ifcs_'] = array_map(function (InterfaceObjectInterface $o) {
                return ['id' => $o->getIfcId(), 'label' => $o->getIfcLabel()];
            }, $this->getNavInterfacesForTgt());
        }
        
        // Get content of subinterfaces if depth is not provided or max depth not yet reached
        if (is_null($depth) || $depth > 0) {
            if (!is_null($depth)) {
                $depth--; // decrease depth by 1
            }
            
            // Prevent infinite loops for reference interfaces when no depth is provided
            // We only need to check LINKTO ref interfaces, because cycles may not exist in regular references (enforced by Ampersand generator)
            // If $depth is provided, no check is required, because recursion is finite
            if ($this->isLinkTo && is_null($depth)) {
                if (in_array($resource->id, $recursionArr[$this->refInterfaceId] ?? [])) {
                    throw new Exception("Infinite loop detected for {$resource} in " . $this->getPath(), 500);
                } else {
                    $recursionArr[$this->refInterfaceId][] = $resource->id;
                }
            }
            
            // Init array for sorting in case of sorting boxes (i.e. SCOLS, SHCOLS, SPCOLS)
            $addSortValues = false;
            if (in_array($this->boxClass, ['SCOLS', 'SHCOLS', 'SPCOLS']) && ($options & Options::INCLUDE_SORT_DATA)) {
                $content['_sortValues_'] = [];
                $addSortValues = true;
            }
            
            // Get sub interface data
            foreach ($this->getSubinterfaces($options) as $subifc) {
                if (!$subifc->crudR()) {
                    continue; // skip subinterface if not given read rights (otherwise exception will be thrown when getting content)
                }
                
                // Add content of subifc
                $content[$subifc->getIfcId()] = $subcontent = $subifc->get($resource, null, $options, $depth, $recursionArr);
                
                // Add sort data if subIfc is univalent
                if ($subifc->isUni() && $addSortValues) {
                    // Use label (value = Atom) or value (value is scalar) to sort objects
                    $content['_sortValues_'][$subifc->getIfcId()] = $subcontent['_label_'] ?? $subcontent ?? null;
                }
            }
        }

        return $content;
    }

    public function create(Resource $src, stdClass $resourceToPost): Resource
    {
        if (!$this->crudC()) {
            throw new Exception("Create not allowed for ". $this->getPath(), 405);
        }
        
        // Use attribute '_id_' if provided
        if (isset($resourceToPost->_id_)) {
            $resource = $this->makeResource($resourceToPost->_id_, $src);
            if ($resource->exists()) {
                throw new Exception("Cannot create resource that already exists", 400);
            }
        } elseif ($this->ifc->isIdent()) {
            $resource = $this->makeResource($src->id, $src);
        } else {
            $resource = $this->makeNewResource($src);
        }
        
        // If interface is editable, also add tuple(src, tgt) in interface relation
        if ($this->isEditable()) {
            $this->add($resource->id, true);
        } else {
            $resource->add();
        }
        
        // Put resource attributes
        return $resource->put($resourceToPost);
    }

    public function put(Resource $src, $value): bool
    {
        if ($this->isUni()) { // expect value to be object or literal
            if (is_array($value)) {
                throw new Exception("Non-array expected but array provided while updating " . $this->getPath(), 400);
            }
            
            if ($this->tgtConcept->isObject()) {
                if (is_null($value) || is_string($value)) { // null object or string
                    $this->set($value);
                } elseif (isset($value->_id_)) { // object with _id_ attribute
                    $this->set($value->_id_);
                } elseif ($this->isIdent()) { // Ident object => no need for object id
                    // go deeper into PUT when interface expression equals 'I'
                    $this->makeResource($src->id, $src)->put($value);
                } else {
                    throw new Exception("Cannot identify provided object while updating " . $this->getPath(), 400);
                }
            } else { // expect value to be literal (i.e. non-object) or null
                $this->set($value);
            }
        } else { // expect value to be array
            if (!is_array($value)) {
                throw new Exception("Array expected but not provided while updating " . $this->getPath(), 400);
            }
            
            // First empty existing list
            $this->removeAll();
            
            // Add provided values
            foreach ($value as $item) {
                if ($this->tgtConcept->isObject()) { // expect item to be object
                    if (!is_object($item)) {
                        throw new Exception("Object expected but " . gettype($item) . " provided while updating " . $this->getPath(), 400);
                    }
                    
                    if (is_string($item)) { // string
                        $this->add($item);
                    } elseif (isset($item->_id_)) { // object with _id_ attribute
                        $this->add($item->_id_);
                    } else {
                        throw new Exception("Cannot identify provided object while updating " . $this->getPath(), 400);
                    }
                } else { // expect item to be literal (i.e. non-object) or null
                    $this->add($item);
                }
            }
        }
        
        return true;
    }

    public function delete(Resource $tgtAtom): bool
    {
        if (!$this->crudD()) {
            throw new Exception("Delete not allowed for ". $this->getPath(), 405);
        }
        
        // Perform delete
        $tgtAtom->concept->deleteAtom($tgtAtom);

        return true;
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
            , 'relation' => $this->relation()->signature ?? ''
            , 'flipped' => $this->relationIsFlipped
            , 'ref' => $this->refInterfaceId
            , 'root' => $this->isRoot()
            , 'public' => $this->isPublic()
            , 'roles' => implode(',', $this->ifcRoleNames)
            ];
    }

    /**
     * Undocumented function
     *
     * @param \Ampersand\Interfacing\Resource $src
     * @return \Ampersand\Core\Atom[]
     */
    protected function getTgtAtoms(Resource $src): array
    {
        return $this->getTgtResources($src, false);
    }

    /**
     * Return list of target atoms
     * @param \Ampersand\Interfacing\Resource $src
     * @param bool $returnClass specifies if method returns list of Resources (true) or Atoms (false)
     * @param string|null $selectTgt
     * @return \Ampersand\Interfacing\Resource[]|\Ampersand\Core\Atom[]
     */
    protected function getTgtResources(Resource $src, bool $returnResource = true, string $selectTgt = null): array
    {
        $tgts = [];

        // If interface isIdent (i.e. expr = I[Concept]), and no epsilon is required (i.e. srcConcept equals tgtConcept of parent ifc) we can return the src
        if ($this->isIdent() && $this->srcConcept === $src->concept) {
            $tgts[] = $returnResource ? $this->makeResource($src->id, $src) : new Atom($src->id, $this->tgtConcept);
        } else {
            // Try to get tgt atom from src query data (in case of uni relation in same table)
            $tgtId = $src->getQueryData('ifc_' . $this->id, $exists); // column is prefixed with ifc_ in query data
            if ($exists) {
                if (!is_null($tgtId)) {
                    $tgts[] = $returnResource ? $this->makeResource($tgtId, $src) : new Atom($src->id, $this->tgtConcept);
                }
            // Evaluate interface expression
            } else {
                foreach ((array) $this->plug->executeIfcExpression($this, $src) as $row) {
                    $tgts[] = $returnResource ? $this->makeResource($row['tgt'], $src)->setQueryData($row) : new Atom($src->id, $this->tgtConcept);
                }
            }
        }

        // Integrity check
        if ($this->isUni() && count($tgts) > 1) {
            throw new Exception("Univalent (sub)interface returns more than 1 resource: " . $this->getPath(), 500);
        }

        // If specific target is specified, pick that one out
        if (!is_null($selectTgt)) {
            return array_filter($tgts, function (Atom $item) use ($selectTgt) {
                return $item->id == $selectTgt;
            });
        }
        
        return $tgts;
    }

    /**
     * Resource factory. Instantiates a new target resource
     *
     * @param string $resourceId
     * @param \Ampersand\Interfacing\Resource $parent
     * @return \Ampersand\Interfacing\Resource
     */
    protected function makeResource(string $resourceId, Resource $parent): Resource
    {
        return new Resource($resourceId, $this->tgtConcept, $this, $parent);
    }
}
