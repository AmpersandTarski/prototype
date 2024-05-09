<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Frontend;

use Ampersand\Frontend\MenuType;

interface FrontendInterface
{
    public function getMenuItems(MenuType $menu): array;

    public function getNavMenuItems(): array;

    public function getNavToResponse($case): ?string;

    public function setNavToResponse(string $navTo, string $case = 'COMMIT'): void;

    /**
     * Determine if frontend app needs to refresh the session information (like navigation bar, roles, etc)
     *
     * True when session variable is affected in a committed transaction
     * False otherwise
     */
    public function getSessionRefreshAdvice(): bool;
}
