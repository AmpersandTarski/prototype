<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

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

    /**
     * Add menu items for navigation menus
     */
    public function reinstallNavigationMenus(Model $model): Installer
    {
        $this->logger->info("(Re)install default navigation menus");

        // TODO: add function to clear/delete current nav menu population

        // MainMenu (i.e. all UI interfaces with SESSION as src concept)
        $mainMenu = $model->getConcept(ProtoContext::CPT_NAV_MENU)->makeAtom('MainMenu')->add();
        $mainMenu->link('Main menu', ProtoContext::REL_NAV_LABEL)->add();
        $mainMenu->link($mainMenu, ProtoContext::REL_NAV_IS_VISIBLE)->add(); // make visible by default
        $mainMenu->link($mainMenu, ProtoContext::REL_NAV_IS_PART_OF)->add();
        $i = '0';
        foreach ($model->getAllInterfaces() as $ifc) {
            // Skip API and non-readable interfaces
            if ($ifc->isAPI() || !$ifc->getIfcObject()->crudR()) {
                continue;
            }

            if ($ifc->getSrcConcept()->isSession()) {
                $i++;
                $menuItem = $model->getConcept(ProtoContext::CPT_NAV_ITEM)->makeAtom($ifc->getId())->add();
                $menuItem->link($ifc->getLabel(), ProtoContext::REL_NAV_LABEL)->add();
                $menuItem->link($menuItem, ProtoContext::REL_NAV_IS_VISIBLE)->add(); // make visible by default
                $menuItem->link($ifc->getId(), ProtoContext::REL_NAV_IFC)->add();
                $menuItem->link((string) $i, ProtoContext::REL_NAV_SEQ_NR)->add();
                $menuItem->link($mainMenu, ProtoContext::REL_NAV_SUB_OF)->add();
            }
        }

        return $this;
    }
}
