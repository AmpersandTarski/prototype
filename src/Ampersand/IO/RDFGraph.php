<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Ampersand\Core\Concept;
use Ampersand\Core\Relation;
use Ampersand\Misc\AcceptHeader;
use Ampersand\Misc\Settings;
use Ampersand\Model;
use Exception;

class RDFGraph extends \EasyRdf\Graph
{
    public function __construct(Model $model, Settings $settings)
    {
        // TODO: add namespace of context here
        $graphURI = 'my-app';

        parent::__construct($graphURI);

        // Set prefixes
        \EasyRdf\RdfNamespace::set('app', "{$graphURI}#");

        // Ampersand CONCEPT --> owl:Class
        foreach ($model->getAllConcepts() as $concept) {
            $this->addConcept($concept);
        }

        // Ampersand RELATION --> owl:Property
        foreach ($model->getRelations() as $relation) {
            $this->addRelation($relation);
        }
    }

    protected function addOntology(Settings $settings): void
    {
        /** @var \EasyRdf\Resource $ontology */
        $ontology = $this->resource('app:ontology', 'owl:Ontology');
        $ontology->set('dc:title', $settings->get('global.contextName'));
        $ontology->set('owl:versionInfo', $settings->get('global.version'));
        $ontology->set('dc:description', "TODO");
    }

    protected function addConcept(Concept $concept): void
    {
        // owl:Class
        if ($concept->isObject()) {
            /** @var \EasyRdf\Resource $cptResource */
            $cptResource = $this->resource("app:{$concept->getId()}", 'owl:Class');
            $cptResource->set('rdfs:label', $concept->label);
            foreach ($concept->getGeneralizations(true) as $generalization) {
                $cptResource->addResource('rdfs:subClassOf', "app:{$generalization->getId()}");
            }
        }

        // Scalar are not expressed as owl:Class, but are expressed as range of an owl:DatatypeProperty
    }

    protected function addRelation(Relation $relation): void
    {
        if (!$relation->srcConcept->isObject()) {
            if (!$relation->tgtConcept->isObject()) {
                // Property from and to a datatype
                // Skip this relation, because it can't be expressed in owl?
                return;
            } else {
                // TODO: flip relation
                throw new Exception("Case where SRC concept of relation is scalar is not yet supported: {$relation}", 501);
            }
        }

        $type = $relation->tgtConcept->isObject() ? 'owl:ObjectProperty' : 'owl:DatatypeProperty';

        /** @var \EasyRdf\Resource $relationResource */
        $relationUniqueName = "{$relation->srcConcept->getId()}-{$relation->name}"; // TODO: also add tgt concept, because full signature must be unique
        $relationResource = $this->resource("app:{$relationUniqueName}", $type);
        $relationResource->set('rdfs:label', $relation->name);

        // Domain
        $domain = $this->resource("app:{$relation->srcConcept->getId()}");
        $relationResource->addResource('rdfs:domain', $domain);
        
        // Domain cardinality contraints
        $min = $relation->isTot ? 1 : 0;
        $max = $relation->isUni ? 1 : -1;
        $this->addCardinalityConstraint($domain, $relationResource, $min, $max);

        // Range
        if ($relation->tgtConcept->isObject()) {
            $range = $this->resource("app:{$relation->tgtConcept->getId()}");
            $relationResource->addResource('rdfs:range', $range);
            
            // Cardinality constraints
            $min = $relation->isSur ? 1 : 0;
            $max = $relation->isInj ? 1 : -1;
            $this->addCardinalityConstraint($range, $relationResource, $min, $max);
        } else {
            $relationResource->addResource('rdfs:range', $relation->tgtConcept->getDatatype('xml'));
            // Skip cardinality constraints on range
        }
    }

    protected function addCardinalityConstraint(\EasyRdf\Resource $class, \EasyRdf\Resource $property, int $min, int $max): \EasyRdf\Resource
    {
        // Checks
        if ($min < 0) {
            throw new Exception("Unsupported value '{$min}' for minimal cardinality. Value must be >= 0.", 500);
        }
        if ($max < -1) {
            throw new Exception("Unsupported value '{$max}' for maximum cardinality. Value must be >= 0 or -1 to specify unbounded", 500);
        }
        if ($min === 0 && $max === -1) {
            // Default owl cardinalities, no rescriction is needed
            return $class;
        }

        // owl:Restriction
        /** @var \EasyRdf\Resource $restriction */
        $restriction = $this->newBNode('owl:Restriction');
        $restriction->addResource('owl:onProperty', $property);
        $class->addResource('rdfs:subClassOf', $restriction); // make the class a subclass of the new restriction class

        if ($min === $max) {
            // owl:cardinality (i.e. exact cardinality)
            $restriction->set('owl:cardinality', $max);
        } else {
            // owl:minCardinality
            if ($min > 0) {
                $restriction->set('owl:minCardinality', $min);
            }
            // owl:maxCardinality
            if ($max !== -1) {
                $restriction->set('owl:maxCardinality', $max);
            }
        }
        return $restriction;
    }

    /**
     * Perform content negotiation based on accept header parameter and return a response format
     *
     * @param string $acceptHeader A HTTP accept header string
     * @return \EasyRdf\Format
     */
    public static function getResponseFormat(string $acceptHeader): \EasyRdf\Format
    {
        if (empty($acceptHeader)) {
            $acceptHeader = 'text/html'; // default format
        }
        $acceptHeader = new AcceptHeader($acceptHeader); // parses the accept header string and returns an array of accepted mimetypes in order of most preferred to least preferred.
        $rdfFormat = null;
        foreach ($acceptHeader as $mimetype) {
            try {
                $rdfFormat = \EasyRdf\Format::getFormat($mimetype['raw']);
                break;
            } catch (\EasyRdf\Exception $e) {
                // unsupported mimetype, check next.
            }
        }

        if ($rdfFormat === null) {
            throw new Exception("No supported formats in accept header. Supported: " . \EasyRdf\Format::getHttpAcceptHeader(), 400);
        }
        return $rdfFormat;
    }
}
