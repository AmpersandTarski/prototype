<?php

namespace Ampersand\Exception;

use Ampersand\Exception\AmpersandException;

/**
 * Use this exception for situations that SHOULD not occur in the prototype framework
 * It is an indication that there is a bug in the source code that needs to be fixed
 * by the Ampersand development team
 */
class FatalException extends AmpersandException
{
}
