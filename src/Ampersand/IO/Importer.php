<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Ampersand\Core\Atom;
use Ampersand\Core\Link;
use Psr\Log\LoggerInterface;
use stdClass;
use Ampersand\AmpersandApp;

class Importer
{

    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Reference to application object
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $app;

    /**
     * Constructor
     *
     * @param \Ampersand\AmpersandApp $app
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $options
     */
    public function __construct(AmpersandApp $app, LoggerInterface $logger, array $options = [])
    {
        $this->logger = $logger;
        $this->app = $app;
    }
    
    /**
     * Import population (i.e. all provided atoms and links)
     *
     * @return void
     */
    public function importPopulation(stdClass $population)
    {
        $this->logger->info("Start import of population");

        // Before importing, check if all provided concepts and relations are defined
        $this->logger->debug("Checking if all concepts for which population is provided are defined");
        foreach ($population->atoms as $pop) {
            if (!empty($pop->atoms)) {
                $this->app->getModel()->getConcept($pop->concept);
            }
        }
        $this->logger->debug("Checking if all relations for which population is provided are defined");
        foreach ($population->links as $pop) {
            if (!empty($pop->links)) {
                $this->app->getRelation($pop->relation);
            }
        }

        $this->importAtoms($population->atoms);
        $this->importLinks($population->links);

        $this->logger->info("End import of population");
    }

    /**
     * Import concept population (i.e. all provided atoms)
     *
     * @param array $atoms
     * @return void
     */
    public function importAtoms(array $atoms)
    {
        $this->logger->info("Importing concept populations");

        foreach ($atoms as $population) {
            if (empty($population->atoms)) {
                continue; // Skip when nothing to import
            }

            $concept = $this->app->getModel()->getConcept($population->concept);
            $total = count($population->atoms);
            $this->logger->debug("Importing {$total} atoms for concept {$concept}");
            
            foreach ($population->atoms as $atomId) {
                $atom = new Atom($atomId, $concept);
                $atom->add();
            }
            set_time_limit((int) ini_get('max_execution_time')); // reset time limit counter to handle large amounts of default population queries.
        }
    }

    /**
     * Import relation population (i.e. all provided links)
     *
     * @param array $links
     * @return void
     */
    public function importLinks(array $links)
    {
        $this->logger->info("Importing relation populations");
        
        foreach ($links as $population) {
            if (empty($population->links)) {
                continue; // Skip when nothing to import
            }

            $relation = $this->app->getRelation($population->relation);
            $total = count($population->links);
            $this->logger->debug("Importing {$total} links for relation {$relation}");
            
            foreach ($population->links as $pair) {
                $link = new Link($relation, new Atom($pair->src, $relation->srcConcept), new Atom($pair->tgt, $relation->tgtConcept));
                $link->add();
            }
            set_time_limit((int) ini_get('max_execution_time')); // reset time limit counter to handle large amounts of default population queries.
        }
    }
}
