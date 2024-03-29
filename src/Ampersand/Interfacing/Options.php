<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Options
{

    const
    INCLUDE_NOTHING     = 0b00000000,

    DEFAULT_OPTIONS     = 0b00001111, // default options
    
    INCLUDE_UI_DATA     = 0b00000001, // includes _id_, _label_, _view_ and _path_
    
    INCLUDE_NAV_IFCS    = 0b00000010, // includes _ifcs_
    
    INCLUDE_SORT_DATA   = 0b00000100, // includes _sortVales_

    INCLUDE_REF_IFCS    = 0b00001000,
    
    INCLUDE_LINKTO_IFCS = 0b00010000;

    /**
     * Get resource options using API params
     */
    public static function getFromRequestParams(array $params): int
    {
        $optionsMap = ['metaData' => self::INCLUDE_UI_DATA
                      ,'sortData' => self::INCLUDE_SORT_DATA
                      ,'navIfc' => self::INCLUDE_NAV_IFCS
                      ,'inclLinktoData' => (self::INCLUDE_REF_IFCS | self::INCLUDE_LINKTO_IFCS) // flag both options
                      //,'inclRefIfcs' => self::INCLUDE_REF_IFCS // not a user option!
                      ];
        
        return self::processOptionsMap($optionsMap, $params, self::DEFAULT_OPTIONS);
    }

    /**
     * Set/unset options based on provided params and options map
     */
    protected static function processOptionsMap(array $optionsMap, array $params, int $options = 0): int
    {
        foreach ($optionsMap as $option => $value) {
            if (!isset($params[$option])) {
                continue; // Don't change the default setting
            }

            // If true/false => set/unset the option
            $bool = filter_var($params[$option], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $options = $bool ? $options | $value : $options & ~$value;
        }
        return $options;
    }
}
