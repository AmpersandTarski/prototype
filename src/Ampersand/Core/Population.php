<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Core;

use Psr\Log\LoggerInterface;
use Ampersand\Model;
use Ampersand\Core\Atom;
use Ampersand\Core\Link;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Population
{
    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Ampersand model
     *
     * @var \Ampersand\Model
     */
    protected $model;

    /**
     * List of atoms in this population
     *
     * @var \Ampersand\Core\Atom[]
     */
    protected $atoms = [];

    /**
     * List of links in this population
     *
     * @var \Ampersand\Core\Link[]
     */
    protected $links = [];

    public function __construct(Model $model, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->model = $model;
    }

    public function loadFromPopulationFile(\stdClass $populationFile): Population
    {
        // Load atoms
        foreach ($populationFile->atoms as $population) {
            $concept = $this->model->getConcept($population->concept);
            
            foreach ($population->atoms as $atomId) {
                $this->atoms[] = new Atom($atomId, $concept);
            }
        }
        
        // Load links
        foreach ($populationFile->links as $population) {
            $relation = $this->model->getRelation($population->relation);
            
            foreach ($population->links as $pair) {
                $this->links[] = new Link($relation, new Atom($pair->src, $relation->srcConcept), new Atom($pair->tgt, $relation->tgtConcept));
            }
        }

        return $this;
    }

    /**
     * Fill population object with atoms and links from provided concepts and relations
     *
     * @param \Ampersand\Core\Concept[] $concepts
     * @param \Ampersand\Core\Relation[] $relations
     */
    public function loadExistingPopulation(array $concepts, array $relations): Population
    {
        foreach (array_unique($concepts) as $concept) {
            /** @var \Ampersand\Core\Concept $concept */
            $this->atoms = array_merge($this->atoms, $concept->getAllAtomObjects());
        }
        foreach (array_unique($relations) as $rel) {
            /** @var \Ampersand\Core\Relation $rel */
            $this->links = array_merge($this->links, $rel->getAllLinks());
        }

        return $this;
    }

    public function export(EncoderInterface $encoder, string $format): string
    {
        $conceptPop = [];
        foreach ($this->atoms as $atom) {
            $conceptPop[$atom->concept->name]['atoms'][] = $atom->getId();
        }
        foreach ($conceptPop as $key => $value) {
            $conceptPop[$key]['concept'] = $key;
        }
        
        $relationPop = [];
        foreach ($this->links as $link) {
            $relationPop[$link->relation()->signature]['links'][] = [
                'src' => $link->src()->getId(),
                'tgt' => $link->tgt()->getId()
            ];
        }
        foreach ($relationPop as $key => $value) {
            $relationPop[$key]['relation'] = $key;
        }
        
        return $encoder->encode(
            [ 'atoms' => array_values($conceptPop),
              'links' => array_values($relationPop)
            ],
            $format
        );
    }

    public function import(): Population
    {
        $this->logger->info("Start import of population");
        $this->importAtoms();
        $this->importLinks();
        $this->logger->info("End import of population");

        return $this;
    }

    /**
     * @return \Ampersand\Core\Concept[]
     */
    public function getConcepts(): array
    {
        return array_values(
            array_unique(
                array_map(fn(Atom $atom) => $atom->concept, $this->atoms)
            )
        );
    }

    /**
     * @return \Ampersand\Core\Relation[]
     */
    public function getRelations(): array
    {
        return array_values(
            array_unique(
                array_map(fn(Link $link) => $link->relation(), $this->links)
            )
        );
    }

    protected function importAtoms(): void
    {
        $total = count($this->atoms);
        $i = 0;
        $this->logger->info("Importing {$total} atoms for relation populations");
        foreach ($this->atoms as $atom) {
            $i++;
            $atom->add();

            if ($i % 100 === 0) {
                $this->logger->debug("...{$i}/{$total} imported");
                set_time_limit((int) ini_get('max_execution_time')); // reset time limit counter to handle large amounts of default population queries.
            }
        }
    }

    protected function importLinks(): void
    {
        $total = count($this->links);
        $i = 0;
        $this->logger->info("Importing {$total} links for relation populations");
        foreach ($this->links as $link) {
            $i++;
            $link->add();

            if ($i % 100 === 0) {
                $this->logger->debug("...{$i}/{$total} imported");
                set_time_limit((int) ini_get('max_execution_time')); // reset time limit counter to handle large amounts of default population queries.
            }
        }
    }

    public function filterAtoms(callable $function): Population
    {
        $pop = clone $this;
        $pop->atoms = array_filter($this->atoms, $function);
        return $pop;
    }

    public function filterLinks(callable $function): Population
    {
        $pop = clone $this;
        $pop->links = array_filter($this->links, $function);
        return $pop;
    }
}
