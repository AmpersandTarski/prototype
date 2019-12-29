<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\Model;
use Psr\Log\LoggerInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Installer
{
    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Add/import meta population
     *
     * @param \Ampersand\Model $model
     * @return \Ampersand\Misc\Installer
     */
    public function reinstallMetaPopulation(Model $model): Installer
    {
        $this->logger->info("(Re)install meta population");

        // TODO: add function to clear/delete current meta population
        $model->getMetaPopulation()->import();

        return $this;
    }

    /**
     * Add/import initial population as defined in generated Ampersand model file
     *
     * @param \Ampersand\Model $model
     * @return \Ampersand\Misc\Installer
     */
    public function addInitialPopulation(Model $model): Installer
    {
        $this->logger->info("Add initial population");

        $model->getInitialPopulation()->import();

        return $this;
    }

    /**
     * Add menu items for navigation menus
     *
     * @param \Ampersand\Model $model
     * @return \Ampersand\Misc\Installer
     */
    public function reinstallNavigationMenus(Model $model): Installer
    {
        $this->logger->info("(Re)install default navigation menus");

        // TODO: add function to clear/delete current nav menu population

        // MainMenu (i.e. all UI interfaces with SESSION as src concept)
        $mainMenu = $model->getConceptByLabel('PF_NavMenu')->makeAtom('MainMenu')->add();
        $mainMenu->link('Main menu', 'label[PF_NavMenuItem*PF_Label]')->add();
        $mainMenu->link($mainMenu, 'isVisible[PF_NavMenuItem*PF_NavMenuItem]')->add(); // make visible by default
        $mainMenu->link($mainMenu, 'isPartOf[PF_NavMenuItem*PF_NavMenu]')->add();
        $i = '0';
        foreach ($model->getAllInterfaces() as $ifc) {
            /** @var \Ampersand\Interfacing\Ifc $ifc */
            // Skip API and non-readable interfaces
            if ($ifc->isAPI() || !$ifc->getIfcObject()->crudR()) {
                continue;
            }

            if ($ifc->getSrcConcept()->isSession()) {
                $i++;
                $menuItem = $model->getConceptByLabel('PF_NavMenuItem')->makeAtom($ifc->getId())->add();
                $menuItem->link($ifc->getLabel(), 'label[PF_NavMenuItem*PF_Label]')->add();
                $menuItem->link($menuItem, 'isVisible[PF_NavMenuItem*PF_NavMenuItem]')->add(); // make visible by default
                $menuItem->link($ifc->getId(), 'ifc[PF_NavMenuItem*PF_Interface]')->add();
                $menuItem->link((string) $i, 'seqNr[PF_NavMenuItem*PF_SeqNr]')->add();
                $menuItem->link($mainMenu, 'isSubItemOf[PF_NavMenuItem*PF_NavMenuItem]')->add();
            }
        }

        return $this;
    }
}
