<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Exception;
use Ampersand\Core\Atom;
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
     * @var \Ampersand\Plugs\ViewPlugInterface
     */
    protected $plug;
    
    /**
     * Name (and unique identifier) of view
     * @var string
     */
    public $label;
    
    /**
     * Specifies if this view is defined as default view for $this->concept
     * @var boolean
     */
    protected $isDefault;
    
    /**
     * Specifies the concept for which this view can defined
     * @var string
     */
    protected $forConcept;
    
    /**
     * Array with view segments that are used to build the view
     * @var \Ampersand\Interfacing\ViewSegment[]
     */
    protected $segments = [];
    
    /**
     * View constructor
     *
     * @param array $viewDef
     * @param \Ampersand\Plugs\ViewPlugInterface $plug
     */
    public function __construct($viewDef, ViewPlugInterface $plug)
    {
        $this->plug = $plug;
        
        $this->label = $viewDef['label'];
        $this->forConcept = $viewDef['conceptId'];
        $this->isDefault = $viewDef['isDefault'];
        
        foreach ($viewDef['segments'] as $segment) {
            $this->segments[] = new ViewSegment($segment, $this);
        }
    }
    
    public function getLabel()
    {
        return $this->label;
    }

    public function getPlug(): ViewPlugInterface
    {
        return $this->plug;
    }
    
    /**
     * @param Atom $srcAtom the atom for which to get the view data
     * @return array
     */
    public function getViewData(Atom $srcAtom)
    {
        $viewData = [];
        foreach ($this->segments as $viewSegment) {
            $viewData[$viewSegment->getLabel()] = $viewSegment->getData($srcAtom);
        }
        return $viewData;
    }

    /**
     * Get specific view segment
     *
     * @param string|int $label
     * @return \Ampersand\Interfacing\ViewSegment
     */
    public function getSegment($label): ViewSegment
    {
        foreach ($this->segments as $segment) {
            if ($segment->getLabel() == $label) {
                return $segment;
            }
        }
        throw new Exception("View segment '{$this->label}:{$label}' not found", 500);
    }
    
    /**********************************************************************************************
     *
     * Static functions
     *
     *********************************************************************************************/
    
    /**
     * Return view object
     * @param string $viewLabel
     * @throws Exception if view is not defined
     * @return View
     */
    public static function getView($viewLabel)
    {
        if (!array_key_exists($viewLabel, $views = self::getAllViews())) {
            throw new Exception("View '{$viewLabel}' is not defined", 500);
        }
    
        return $views[$viewLabel];
    }
    
    /**
     * Returns array with all view objects
     * @return View[]
     */
    public static function getAllViews()
    {
        if (!isset(self::$allViews)) {
            throw new Exception("View definitions not loaded yet", 500);
        }
         
        return self::$allViews;
    }
}
