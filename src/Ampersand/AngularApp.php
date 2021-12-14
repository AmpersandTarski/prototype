<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Exception;
use Psr\Log\LoggerInterface;
use Ampersand\AmpersandApp;
use Ampersand\Interfacing\Ifc;
use Ampersand\Interfacing\ResourceList;
use Ampersand\Interfacing\Options;
use Ampersand\Misc\ProtoContext;

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
     */
    public function __construct(AmpersandApp $ampersandApp, LoggerInterface $logger)
    {
        $this->ampersandApp = $ampersandApp;
        $this->logger = $logger;
    }
    
    /**
     * @param string $menu specifies to which part of the menu (navbar) this item belongs to
     * TODO: use enum here
     * @param string $itemUrl location of html template to use as menu item
     * @param callable $function function which returns true/false determining to add the menu item or not
     */
    public function addMenuItem(string $menu, string $itemUrl, callable $function): void
    {
        switch ($menu) {
            case 'ext':
                $this->extMenu[] = ['url' => $itemUrl, 'function' => $function];
                break;
            case 'role':
                $this->roleMenu[] = ['url' => $itemUrl, 'function' => $function];
                break;
            default:
                throw new Exception("Cannot add item to menu. Unknown menu: '{$menu}'", 500);
                break;
        }
    }
    
    public function getMenuItems($menu): array
    {
        $ampersandApp = $this->ampersandApp;

        switch ($menu) {
            // Items for extension menu
            case 'ext':
                $result = array_filter($this->extMenu, function ($item) use ($ampersandApp) {
                    return call_user_func_array($item['function'], [$ampersandApp]); // execute function which determines if item must be added or not
                });
                break;
            // Items for role menu
            case 'role':
                $result = array_filter($this->roleMenu, function ($item) use ($ampersandApp) {
                    return call_user_func_array($item['function'], [$ampersandApp]); // execute function which determines if item must be added or not
                });
                break;
            // Items in menu to create new resources (indicated with + sign)
            case 'new':
                // Filter interfaces that are capable to create new Resources
                $interfaces = array_filter($ampersandApp->getAccessibleInterfaces(), function (Ifc $ifc) {
                    $ifcObj = $ifc->getIfcObject();
                    // crudC, otherwise the atom cannot be created
                    // isIdent (interface expr = I[Concept]), because otherwise a src atom is necesarry, which we don't have wiht +-menu
                    if ($ifcObj->crudC() && $ifcObj->isIdent()) {
                        return true;
                    } else {
                        return false;
                    }
                });

                // Prepare output and group by type
                $result = [];
                foreach ($interfaces as $ifc) {
                    /** @var \Ampersand\Interfacing\Ifc $ifc */
                    $type = $ifc->getTgtConcept()->name;

                    if (!isset($result[$type])) {
                        $result[$type] = ['label' => "New {$ifc->getTgtConcept()->label}", 'ifcs' => []];
                    }

                    $result[$type]['ifcs'][] = ['id' => $ifc->getId()
                                               ,'label' => $ifc->getLabel()
                                               ,'link' => '/' . $ifc->getId()
                                               ,'resourceType' => $type
                                               ];
                }
                break;
            default:
                throw new Exception("Cannot get menu items. Unknown menu: '{$menu}'", 500);
        }

        return array_values($result); // Make sure that a true numeric array is returned
    }

    public function getNavMenuItems(): array
    {
        return ResourceList::makeFromInterface($this->ampersandApp->getSession()->getSessionAtom()->getId(), ProtoContext::IFC_MENU_ITEMS)->get(Options::INCLUDE_NOTHING);
    }

    public function getNavToResponse($case): ?string
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
    
    public function setNavToResponse(string $navTo, string $case = 'COMMIT'): void
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
