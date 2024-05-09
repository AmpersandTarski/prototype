<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Ampersand\Model;
use Ampersand\Core\Atom;
use Ampersand\Misc\ProtoContext;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Role
{
    /**
     * Role identifier
     */
    protected string $id;
    
    /**
     * Name of the role
     */
    protected string $label;
    
    /**
     * List of all rules that are maintained by this role
     * @var \Ampersand\Rule\Rule[]
     */
    protected array $maintains = [];
    
    /**
     * List of all interfaces that are accessible by this role
     * @var \Ampersand\Interfacing\Ifc[]
     */
    protected ?array $interfaces = null;

    /**
     * Reference to Ampersand model
     */
    protected Model $model;
    
    /**
     * Constructor
     */
    public function __construct(array $roleDef, Model $model)
    {
        $this->model = $model;

        $this->setId($roleDef['id']);
        $this->label = $roleDef['name'];
        
        foreach ((array)$roleDef['maintains'] as $ruleName) {
            $this->maintains[] = $model->getRule($ruleName);
        }
    }
    
    /**
     * Function is called when object is treated as a string
     */
    public function __toString(): string
    {
        return $this->label;
    }

    protected function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
    
    /**
     * Get list of rules that are maintained by this role
     *
     * @return \Ampersand\Rule\Rule[]
     */
    public function maintains(): array
    {
        return $this->maintains;
    }
    
    /**
     * Get list of interfaces that are accessible for this role
     *
     * @return \Ampersand\Interfacing\Ifc[]
     */
    public function interfaces(): array
    {
        // If not yet instantiated
        if (is_null($this->interfaces)) {
            // Make role Atom
            $roleAtom = $this->model->getRoleConcept()->makeAtom($this->id);

            // Query and filter (un)defined interfaces
            $ifcAtoms = array_filter(
                $roleAtom->getTargetAtoms(ProtoContext::REL_IFC_ROLES, true),
                function (Atom $ifcAtom) {
                    return $this->model->interfaceExists($ifcAtom->getId());
                }
            );

            // Set interfaces
            $this->interfaces = array_map(function (Atom $ifcAtom) {
                return $this->model->getInterface($ifcAtom->getId());
            }, $ifcAtoms);
        }

        return $this->interfaces;
    }
}
