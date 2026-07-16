<?php

namespace Ampersand\Plugs\MysqlDB\Exception;

use Ampersand\Plugs\MysqlDB\Exception\MysqlQueryException;

/**
 * MySQL/MariaDB error 1213: deadlock found when trying to get lock.
 * The database rolled back the (victim) transaction; the operation may be retried.
 */
class MysqlDeadlockException extends MysqlQueryException
{
}
