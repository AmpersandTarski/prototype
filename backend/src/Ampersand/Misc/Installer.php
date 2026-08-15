<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\Core\Atom;
use Ampersand\Exception\InvalidConfigurationException;
use Ampersand\Interfacing\Ifc;
use Ampersand\Model;
use Psr\Log\LoggerInterface;
use Ampersand\Misc\ProtoContext;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Installer
{
    /**
     * Logger
     */
    protected LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Add/import meta population
     */
    public function reinstallMetaPopulation(Model $model): Installer
    {
        $this->logger->info("(Re)install meta population");

        // TODO: add function to clear/delete current meta population

        $this->cleanupMetaPopulation($model);

        $model->getMetaPopulation()->import();

        return $this;
    }

    public function cleanupMetaPopulation(Model $model): Installer
    {
        // Cleanup interface atoms that are not defined (anymore) in the Ampersand model files
        foreach ($model->getConcept(ProtoContext::CPT_IFC)->getAllAtomObjects() as $ifcAtom) {
            if (!$model->interfaceExists($ifcAtom->getId())) {
                $ifcAtom->delete();
            }
        }

        return $this;
    }

    /**
     * Add/import initial population as defined in generated Ampersand model file
     */
    public function addInitialPopulation(Model $model): Installer
    {
        $this->logger->info("Add initial population");

        $model->getInitialPopulation()->import();

        return $this;
    }

    const
        MENU_GROUPING_NONE      = 'none',
        MENU_GROUPING_BY_TYPE   = 'byType';

    // Fixed atom id for the generated group item, so reinstalling is idempotent
    const GROUP_ATOM_ID = '_MainMenu_lists';

    /**
     * Add menu items for navigation menus
     *
     * Menu structure is presentation, not semantics: the structure is population that a
     * project can override with its own population or the EditNavigationMenu interface.
     * Reinstalling clears the nav menu population and re-applies the default.
     *
     * With $menuGrouping 'byType', SESSION interfaces are split by the type of their
     * expression: target SESSION (task screens, expr ⊆ I[SESSION]) stay top-level;
     * target a domain concept (lists, expr ⊆ V[SESSION*Concept]) group into one submenu.
     */
    public function reinstallNavigationMenus(
        Model $model,
        string $menuGrouping = self::MENU_GROUPING_NONE,
        string $menuGroupLabel = 'Lists'
    ): Installer {
        $this->logger->info("(Re)install default navigation menus (grouping: {$menuGrouping})");

        if (!in_array($menuGrouping, [self::MENU_GROUPING_NONE, self::MENU_GROUPING_BY_TYPE])) {
            throw new InvalidConfigurationException(
                "Unsupported value '{$menuGrouping}' for setting frontend.menuGrouping. Supported values: 'none', 'byType'"
            );
        }

        // Clear current nav menu population, so reinstalling is idempotent and leaves no
        // stale items. NavMenu ISA NavMenuItem, so this also removes the MainMenu atom.
        foreach ($model->getConcept(ProtoContext::CPT_NAV_ITEM)->getAllAtomObjects() as $navItemAtom) {
            $navItemAtom->delete();
        }

        // MainMenu (i.e. all UI interfaces with SESSION as src concept)
        $mainMenu = $model->getConcept(ProtoContext::CPT_NAV_MENU)->makeAtom('MainMenu')->add();
        $mainMenu->link('Main menu', ProtoContext::REL_NAV_LABEL)->add();
        $mainMenu->link($mainMenu, ProtoContext::REL_NAV_IS_VISIBLE)->add(); // make visible by default
        $mainMenu->link($mainMenu, ProtoContext::REL_NAV_IS_PART_OF)->add();

        $menuIfcs = array_filter(
            $model->getAllInterfaces(),
            fn ($ifc) => !$ifc->isAPI() && $ifc->getIfcObject()->crudR() && $ifc->getSrcConcept()->isSession()
        );

        // Split interfaces in top-level items and grouped (list) items
        $topLevelIfcs = $groupedIfcs = [];
        foreach ($menuIfcs as $ifc) {
            if ($menuGrouping === self::MENU_GROUPING_BY_TYPE && !$ifc->getTgtConcept()->isSession()) {
                $groupedIfcs[] = $ifc; // list/reference table (expr ⊆ V[SESSION*Concept])
            } else {
                $topLevelIfcs[] = $ifc; // task screen (expr ⊆ I[SESSION]), or grouping disabled
            }
        }

        $i = 0;
        foreach ($topLevelIfcs as $ifc) {
            $this->addNavMenuItem($model, $ifc, $mainMenu, ++$i);
        }

        if (!empty($groupedIfcs)) {
            $groupItem = $model->getConcept(ProtoContext::CPT_NAV_ITEM)->makeAtom(self::GROUP_ATOM_ID)->add();
            $groupItem->link($menuGroupLabel, ProtoContext::REL_NAV_LABEL)->add();
            $groupItem->link($groupItem, ProtoContext::REL_NAV_IS_VISIBLE)->add(); // make visible by default
            $groupItem->link((string) ++$i, ProtoContext::REL_NAV_SEQ_NR)->add();
            $groupItem->link($mainMenu, ProtoContext::REL_NAV_SUB_OF)->add();

            $j = 0;
            foreach ($groupedIfcs as $ifc) {
                $this->addNavMenuItem($model, $ifc, $groupItem, ++$j);
            }
        }

        return $this;
    }

    protected function addNavMenuItem(Model $model, Ifc $ifc, Atom $parentItem, int $seqNr): void
    {
        $menuItem = $model->getConcept(ProtoContext::CPT_NAV_ITEM)->makeAtom($ifc->getId())->add();
        $menuItem->link($ifc->getLabel(), ProtoContext::REL_NAV_LABEL)->add();
        $menuItem->link($menuItem, ProtoContext::REL_NAV_IS_VISIBLE)->add(); // make visible by default
        $menuItem->link($ifc->getId(), ProtoContext::REL_NAV_IFC)->add();
        $menuItem->link((string) $seqNr, ProtoContext::REL_NAV_SEQ_NR)->add();
        $menuItem->link($parentItem, ProtoContext::REL_NAV_SUB_OF)->add();
    }
}
