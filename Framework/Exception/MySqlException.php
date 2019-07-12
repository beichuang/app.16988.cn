<?php

namespace Framework\Exception;

/**
 * mysql异常类
 */
class MySqlException extends \PDOException
{
    public function __construct($sql, $bindData, $msg)
    {
        parent::__construct($msg.";\r\n".$sql.';'.var_export($bindData, true));
    }
}
