<?php

namespace Ampersand\Plugs\MysqlDB\Exception;

use Ampersand\Exception\FatalException;

/**
 * The database refused a write because it breaks a constraint that the compiler generates
 * with --foreign-keys (Ampersand#1744): MariaDB error 1451 or 1452, a reference to an atom
 * that does not exist. The framework writes atoms before their links and removes links
 * before their atoms, so a refusal means a defect in the framework or a write that bypassed
 * it. Like any FatalException it is not the user's doing; the message tells the user so.
 */
class DatabaseGuardException extends FatalException
{
    public const USER_MESSAGE = "The database refused a change that this application should never make. "
        . "This is a fault in the application, not in what you did. "
        . "Please report it to the supplier of the application, with the time of this message.";
}
