<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

enum RuleType: string
{
    case INV = 'invariant';
    case SIG = 'signal';
}
