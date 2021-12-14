<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Frontend;

enum MenuType: string
{
    case EXT = 'extension';
    case ROLE = 'role';
    case NEW = 'new';
}
