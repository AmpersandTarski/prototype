<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Exception\NotDefined\NotDefinedException;
use Ampersand\Interfacing\ViewSegment;
use Ampersand\Plugs\ViewPlugInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class View
{
    /**
     * Dependency injection of an ViewPlug implementation
     */
    protected ViewPlugInterface $plug;

    /**
     * Name (and unique identifier) of view
     */
    public string $name;
    
    /**
     * Label of view
     */
    public string $label;
    
    /**
     * Specifies if this view is defined as default view for $this->concept
     */
    protected bool $isDefault;
    
    /**
     * Specifies the concept for which this view can defined
     */
    protected string $forConcept;
    
    /**
     * Array with view segments that are used to build the view
     *
     * @var \Ampersand\Interfacing\ViewSegment[]
     */
    protected array $segments = [];
    
    /**
     * Constructor
     */
    public function __construct(array $viewDef, ViewPlugInterface $plug)
    {
        $this->plug = $plug;
        
        $this->name = $viewDef['name'];
        $this->label = $viewDef['label'];
        $this->forConcept = $viewDef['conceptName'];
        $this->isDefault = $viewDef['isDefault'];
        
        foreach ($viewDef['segments'] as $segment) {
            $this->segments[] = new ViewSegment($segment, $this);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }
    
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPlug(): ViewPlugInterface
    {
        return $this->plug;
    }
    
    /**
     * Get view data for specified atom
     */
    public function getViewData(Atom $srcAtom): array
    {
        $viewData = [];
        foreach ($this->segments as $viewSegment) {
            $viewData[$viewSegment->getLabel()] = $viewSegment->getData($srcAtom);
        }
        return $viewData;
    }

    /**
     * Get specific view segment
     */
    public function getSegment(string|int $label): ViewSegment
    {
        foreach ($this->segments as $segment) {
            if ($segment->getLabel() == $label) {
                return $segment;
            }
        }
        throw new NotDefinedException("View segment '{$this->name}:{$label}' not found");
    }
}
