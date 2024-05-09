<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Frontend;

use Ampersand\Exception\InvalidOptionException;
use Ampersand\Frontend\MenuType;

class MenuItemRegistry
{
    /**
     * List of items for the extensions menu (in navbar)
     */
    public static array $extMenu = [];
    
    /**
     * List of items for the role menu (in navbar)
     */
    public static array $roleMenu = [];

    /**
     * @param \Ampersand\Frontend\MenuType $menu specifies to which part of the menu (navbar) this item belongs to
     * @param string $itemUrl location of html template to use as menu item
     * @param callable $function function which returns true/false determining to add the menu item or not
     */
    public static function addMenuItem(MenuType $menu, string $itemUrl, callable $function): void
    {
        match ($menu) {
            MenuType::EXT => self::$extMenu[] = ['url' => $itemUrl, 'function' => $function],
            MenuType::ROLE => self::$roleMenu[] = ['url' => $itemUrl, 'function' => $function],
            MenuType::NEW => throw new InvalidOptionException("Cannot add custom menu items to menu 'new'")
        };
    }
}
