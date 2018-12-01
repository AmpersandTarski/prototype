<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Exception;
use Psr\Log\LoggerInterface;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\AmpersandApp;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class AngularApp
{
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to Ampersand app of which this frontend app (Angular) belongs to
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $ampersandApp;
    
    /**
     * List of items for the extensions menu (in navbar)
     *
     * @var array
     */
    protected $extMenu = [];
    
    /**
     * List of items for the refresh menu (in navbar)
     *
     * @var array
     */
    protected $refreshMenu = [];
    
    /**
     * List of items for the role menu (in navbar)
     *
     * @var array
     */
    protected $roleMenu = [];

    /**
     * Contains information for the front-end to navigate the user in a certain case (e.g. after COMMIT)
     *
     * @var array
     */
    protected $navToResponse = [];

    /**
     * Constructor
     *
     * @param \Ampersand\AmpersandApp $ampersandApp
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(AmpersandApp $ampersandApp, LoggerInterface $logger)
    {
        $this->ampersandApp = $ampersandApp;
        $this->logger = $logger;
    }
    
    /**
     * @param string $menu specifies to which part of the menu (navbar) this item belongs to
     * @param string $itemUrl location of html template to use as menu item
     * @param callable function which returns true/false determining to add the menu item or not
     */
    public function addMenuItem(string $menu, string $itemUrl, callable $function)
    {
        switch ($menu) {
            case 'ext':
                $this->extMenu[] = ['url' => $itemUrl, 'function' => $function];
                break;
            case 'refresh':
                $this->refreshMenu[] = ['url' => $itemUrl, 'function' => $function];
                break;
            case 'role':
                $this->roleMenu[] = ['url' => $itemUrl, 'function' => $function];
                break;
            default:
                throw new Exception("Cannot add item to menu. Unknown menu: '{$menu}'", 500);
                break;
        }
    }
    
    public function getMenuItems($menu)
    {
        $ampersandApp = $this->ampersandApp;

        switch ($menu) {
            // Items for extension menu
            case 'ext':
                return array_filter($this->extMenu, function ($item) use ($ampersandApp) {
                    return call_user_func_array($item['function'], [$ampersandApp]); // execute function which determines if item must be added or not
                });
            
            // Items for refresh menu
            case 'refresh':
                return array_filter($this->refreshMenu, function ($item) use ($ampersandApp) {
                    return call_user_func_array($item['function'], [$ampersandApp]); // execute function which determines if item must be added or not
                });
            
            // Items for role menu
            case 'role':
                return array_filter($this->roleMenu, function ($item) use ($ampersandApp) {
                    return call_user_func_array($item['function'], [$ampersandApp]); // execute function which determines if item must be added or not
                });

            // Items in menu to create new resources (indicated with + sign)
            case 'new':
                // Filter interfaces that are capable to create new Resources
                $interfaces = array_filter($ampersandApp->getAccessibleInterfaces(), function (InterfaceObjectInterface $ifc) {
                    // crudC, otherwise the atom cannot be created
                    // isIdent (interface expr = I[Concept]), because otherwise a src atom is necesarry, which we don't have wiht +-menu
                    if ($ifc->crudC() && $ifc->isIdent()) {
                        return true;
                    } else {
                        return false;
                    }
                });

                // Prepare output and group by type
                $result = [];
                foreach ($interfaces as $ifc) {
                    /** @var \Ampersand\Interfacing\InterfaceObjectInterface $ifc */
                    $type = $ifc->getTargetConcept()->name; // or sort by classification tree: $sort = $ifc->getTargetConcept()->getLargestConcept()->name;

                    if (!isset($result[$type])) {
                        $result[$type] = ['label' => "New {$ifc->getTargetConcept()->label}", 'ifcs' => []];
                    }

                    $result[$type]['ifcs'][] = ['id' => $ifc->getIfcId()
                                               ,'label' => $ifc->getIfcLabel()
                                               ,'link' => '/' . $ifc->getIfcId()
                                               ,'resourceType' => $type
                                               ];
                }
                return $result;

            // Top level items in menu bar
            case 'top':
                $interfaces = array_filter($ampersandApp->getAccessibleInterfaces(), function (InterfaceObjectInterface $ifc) {
                    if ($ifc->getSourceConcept()->isSession() && $ifc->crudR()) {
                        return true;
                    } else {
                        return false;
                    }
                });

                return array_map(function (InterfaceObjectInterface $ifc) {
                    return [ 'id' => $ifc->getIfcId()
                           , 'label' => $ifc->getIfcLabel()
                           , 'link' => '/' . $ifc->getIfcId()
                           ];
                }, $interfaces);
                
            default:
                throw new Exception("Cannot get menu items. Unknown menu: '{$menu}'", 500);
        }
    }

    public function getNavToResponse($case)
    {
        switch ($case) {
            case 'COMMIT':
            case 'ROLLBACK':
                if (array_key_exists($case, $this->navToResponse)) {
                    return $this->navToResponse[$case];
                } else {
                    return null;
                }
                break;
            default:
                throw new Exception("Unsupported case '{$case}' to getNavToResponse", 500);
        }
    }
    
    public function setNavToResponse($navTo, $case = 'COMMIT')
    {
        switch ($case) {
            case 'COMMIT':
            case 'ROLLBACK':
                $this->navToResponse[$case] = $navTo;
                break;
            default:
                throw new Exception("Unsupported case '{$case}' to setNavToResponse", 500);
        }
    }

    /**
     * Determine if frontend app needs to refresh the session information (like navigation bar, roles, etc)
     *
     * True when session variable is affected in a committed transaction
     * False otherwise
     *
     * @return boolean
     */
    public function getSessionRefreshAdvice(): bool
    {
        static $skipRels = ['lastAccess[SESSION*DateTime]']; // these relations do not result in a session refresh advice

        $affectedRelations = [];
        foreach ($this->ampersandApp->getTransactions() as $transaction) {
            if (!$transaction->isCommitted()) {
                continue;
            }
            $affectedRelations = array_merge($affectedRelations, $transaction->getAffectedRelations());
        }
        
        foreach (array_unique($affectedRelations) as $relation) {
            // Advise session refresh when src or tgt concept of this relation is SESSION
            if (($relation->srcConcept->isSession() || $relation->tgtConcept->isSession())
                && !in_array($relation->getSignature(), $skipRels)) {
                return true;
            }
        }

        return false;
    }
}
