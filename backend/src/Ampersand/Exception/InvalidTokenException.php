<?php

namespace Ampersand\Exception;

use Ampersand\Exception\AmpersandException;

/**
 * A bearer token that the door does not accept (OK-32). The API answers 401.
 */
class InvalidTokenException extends AmpersandException
{
}
