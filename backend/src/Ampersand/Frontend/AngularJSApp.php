<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Frontend;

use Ampersand\AmpersandApp;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\InvalidOptionException;
use Ampersand\Frontend\FrontendInterface;
use Ampersand\Frontend\MenuType;
use Ampersand\Interfacing\Ifc;
use Ampersand\Interfacing\ResourceList;
use Ampersand\Interfacing\Options;
use Ampersand\Misc\ProtoContext;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class AngularJSApp implements FrontendInterface
{
    /**
     * Reference to Ampersand app of which this frontend app (Angular) belongs to
     */
    protected AmpersandApp $ampersandApp;

    /**
     * Contains information for the front-end to navigate the user in a certain case (e.g. after COMMIT)
     */
    protected array $navToResponse = [];

    /**
     * Constructor
     */
    public function __construct(AmpersandApp $ampersandApp)
    {
        $this->ampersandApp = $ampersandApp;
    }
    
    public function getMenuItems(MenuType $menu): array
    {
        $ampersandApp = $this->ampersandApp;

        switch ($menu) {
            // Items for extension menu
            case MenuType::EXT:
                $result = array_filter(MenuItemRegistry::$extMenu, function ($item) use ($ampersandApp) {
                    return call_user_func_array($item['function'], [$ampersandApp]); // execute function which determines if item must be added or not
                });
                break;
            // Items for role menu
            case MenuType::ROLE:
                $result = array_filter(MenuItemRegistry::$roleMenu, function ($item) use ($ampersandApp) {
                    return call_user_func_array($item['function'], [$ampersandApp]); // execute function which determines if item must be added or not
                });
                break;
            // Items in menu to create new resources (indicated with + sign)
            case MenuType::NEW:
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
                throw new FatalException("Cannot get menu items. Unknown menu: '{$menu->value}'");
        }

        return array_values($result); // Make sure that a true numeric array is returned
    }

    public function getNavMenuItems(): array
    {
        return ResourceList::makeFromInterface(
            $this->ampersandApp->getSession()->getSessionAtom()->getId(),
            ProtoContext::IFC_MENU_ITEMS
        )->get(Options::INCLUDE_NOTHING);
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
                throw new FatalException("Unsupported case '{$case}' to getNavToResponse");
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
                throw new InvalidOptionException("Unsupported case '{$case}' to setNavToResponse");
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
        // These relations do not result in a session refresh advice
        static $skipRels = [
            ProtoContext::REL_SESSION_LAST_ACCESS,
        ];

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
