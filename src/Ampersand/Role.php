<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Exception;
use Ampersand\Rule\Rule;
use Ampersand\Model;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Role
{
    /**
     * Contains all role definitions
     * @var Role[]
     */
    private static $allRoles;
    
    /**
     * Role identifier
     * @var int
     */
    protected $id;
    
    /**
     * Name of the role
     * @var string
     */
    public $label;
    
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
     * Private function to prevent outside instantiation of roles
     * Use Role::getRoleById($roleId) or Role::getRoleByName($roleName)
     *
     * @param array $roleDef
     * @param \Ampersand\Model $model
     */
    private function __construct($roleDef, Model $model)
    {
        $this->id = $roleDef['id'];
        $this->label = $roleDef['name'];
        
        foreach ((array)$roleDef['maintains'] as $ruleName) {
            $this->maintains[] = Rule::getRule($ruleName);
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
    
    /**********************************************************************************************
     *
     * Static functions
     *
     *********************************************************************************************/
    
    /**
     * Return role object
     * @param int $roleId
     * @throws Exception
     * return Role
     */
    public static function getRoleById($roleId)
    {
        if (!is_int($roleId)) {
            throw new Exception("No valid role id provided. Role id must be an integer", 500);
        }
        
        foreach (self::getAllRoles() as $role) {
            if ($role->id == $roleId) {
                return $role;
            }
        }
        
        throw new Exception("Role with id '{$roleId}' is not defined", 500);
    }
    
    /**
     * Return role object
     * @param string $roleName
     * @throws Exception if role is not defined
     * @return \Ampersand\Role
     */
    public static function getRoleByName($roleName): Role
    {
        if (!array_key_exists($roleName, $roles = self::getAllRoles())) {
            throw new Exception("Role '{$roleName}' is not defined", 500);
        }
    
        return $roles[$roleName];
    }
    
    /**
     * Returns array with all role objects
     * @return Role[]
     */
    public static function getAllRoles()
    {
        if (!isset(self::$allRoles)) {
            throw new Exception("Role definitions not loaded yet", 500);
        }
         
        return self::$allRoles;
    }
    
    /**
     * Import all role definitions from json file and instantiate Role objects
     *
     * @param string $fileName containing the Ampersand role definitions
     * @param \Ampersand\Model $model
     * @return void
     */
    public static function setAllRoles(string $fileName, Model $model)
    {
        self::$allRoles = [];

        $allRoleDefs = (array) json_decode(file_get_contents($fileName), true);
        
        foreach ($allRoleDefs as $roleDef) {
            self::$allRoles[$roleDef['name']] = new Role($roleDef, $model);
        }
    }
}
