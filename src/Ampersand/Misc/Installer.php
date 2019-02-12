<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\AmpersandApp;
use Ampersand\Core\Atom;
use Ampersand\Interfacing\Ifc;
use Ampersand\IO\Importer;
use Ampersand\Model;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Ampersand\Role;
use Ampersand\Core\Concept;

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
     * Reference to app
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $ampersandApp;

    /**
     * Constructor
     *
     * @param \Ampersand\AmpersandApp $ampersandApp
     */
    public function __construct(AmpersandApp $ampersandApp, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->ampersandApp = $ampersandApp;
    }

    /**
     * Undocumented function
     *
     * @return \Ampersand\Misc\Installer
     */
    public function reinstallMetaPopulation(): Installer
    {
        $this->logger->info("(Re)install meta population");

        $transaction = $this->ampersandApp->newTransaction();

        // TODO: add function to clear/delete current meta population
        $this->addMetaPopulation();

        $transaction->runExecEngine()->close(false, false);
        if ($transaction->isRolledBack()) {
            throw new Exception("Meta population does not satisfy invariant rules. See log files", 500);
        }
        return $this;
    }

    public function reinstallNavigationMenus(): Installer
    {
        $this->logger->info("(Re)install default navigation menus");

        $transaction = $this->ampersandApp->newTransaction();

        // TODO: add function to clear/delete current nav menu population
        $this->addNavMenuItems();

        $transaction->runExecEngine()->close(false, false);
        if ($transaction->isRolledBack()) {
            throw new Exception("Meta population does not satisfy invariant rules. See log files", 500);
        }
        return $this;
    }

    public function addInitialPopulation(Model $model, bool $ignoreInvariantRules = false): Installer
    {
        $this->logger->info("Add initial population");

        $transaction = $this->ampersandApp->newTransaction();

        $decoder = new JsonDecode(false);
        $population = $decoder->decode(file_get_contents($model->getFilePath('populations')), JsonEncoder::FORMAT);
        $importer = new Importer($this->logger);
        $importer->importPopulation($population);

        // Close transaction
        $transaction->runExecEngine()->close(false, $ignoreInvariantRules);
        if ($transaction->isRolledBack()) {
            throw new Exception("Initial installation does not satisfy invariant rules. See log files", 500);
        }

        return $this;
    }

    /**
     * Add model meta population (experimental functionality)
     * TODO: replace by meatgrinder in Ampersand generator
     *
     * @return void
     */
    protected function addMetaPopulation(): void
    {
        // Add roles
        foreach (Role::getAllRoles() as $role) {
            Concept::getRoleConcept()->makeAtom($role->label)->add();
        }

        // Add interfaces
        foreach (Ifc::getAllInterfaces() as $ifc) {
            /** @var \Ampersand\Interfacing\Ifc $ifc */
            $ifcAtom = Atom::makeAtom($ifc->getId(), 'PF_Interface')->add();
            $ifcAtom->link($ifc->getLabel(), 'label[PF_Interface*PF_Label]')->add();
            foreach ($ifc->getRoleNames() as $roleName) {
                $ifcAtom->link($roleName, 'pf_ifcRoles[PF_Interface*PF_Role]')->add();
            }
            if ($ifc->isPublic()) {
                $ifcAtom->link($ifcAtom, 'isPublic[PF_Interface*PF_Interface]')->add();
            }
            if ($ifc->isAPI()) {
                $ifcAtom->link($ifcAtom, 'isAPI[PF_Interface*PF_Interface]')->add();
            }
        }
    }

    /**
     * Add menu items for navigation menus
     *
     * @return void
     */
    protected function addNavMenuItems(): void
    {
        // MainMenu (i.e. all UI interfaces with SESSION as src concept)
        $mainMenu = Atom::makeAtom('MainMenu', 'PF_NavMenu')->add();
        $mainMenu->link('Main menu', 'label[PF_NavMenuItem*PF_Label]')->add();
        $mainMenu->link($mainMenu, 'isVisible[PF_NavMenuItem*PF_NavMenuItem]')->add(); // make visible by default
        $mainMenu->link($mainMenu, 'isPartOf[PF_NavMenuItem*PF_NavMenu]')->add();
        $i = '0';
        foreach (Ifc::getAllInterfaces() as $ifc) {
            /** @var \Ampersand\Interfacing\Ifc $ifc */
            // Skip API and non-readable interfaces
            if ($ifc->isAPI() || !$ifc->getIfcObject()->crudR()) {
                continue;
            }

            if ($ifc->getSrcConcept()->isSession()) {
                $i++;
                $menuItem = Atom::makeAtom($ifc->getId(), 'PF_NavMenuItem')->add();
                $menuItem->link($ifc->getLabel(), 'label[PF_NavMenuItem*PF_Label]')->add();
                $menuItem->link($menuItem, 'isVisible[PF_NavMenuItem*PF_NavMenuItem]')->add(); // make visible by default
                $menuItem->link($ifc->getId(), 'ifc[PF_NavMenuItem*PF_Interface]')->add();
                $menuItem->link($i, 'seqNr[PF_NavMenuItem*PF_SeqNr]')->add();
                $menuItem->link($mainMenu, 'isSubItemOf[PF_NavMenuItem*PF_NavMenuItem]')->add();
            }
        }
    }
}
