<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Ampersand\Model;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Role
{
    /**
     * Role identifier
     * @var string
     */
    protected $id;
    
    /**
     * Name of the role
     * @var string
     */
    protected $label;
    
    /**
     * List of all rules that are maintained by this role
     * @var \Ampersand\Rule\Rule[]
     */
    protected $maintains = [];
    
    /**
     * List of all interfaces that are accessible by this role
     * @var \Ampersand\Interfacing\Ifc[]
     */
    protected $interfaces = [];
    
    /**
     * Constructor of role
     *
     * @param array $roleDef
     * @param \Ampersand\Model $model
     */
    public function __construct($roleDef, Model $model)
    {
        $this->setId($roleDef['id']);
        $this->label = $roleDef['name'];
        
        foreach ((array)$roleDef['maintains'] as $ruleName) {
            $this->maintains[] = $model->getRule($ruleName);
        }
        
        foreach ($roleDef['interfaces'] as $ifcId) {
            $this->interfaces[] = $model->getInterface($ifcId);
        }
    }
    
    /**
     * Function is called when object is treated as a string
     *
     * @return string
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

    public function getLabel()
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
        return $this->interfaces;
    }
}
