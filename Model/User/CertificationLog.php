<?php
namespace Model\User;

use Lib\Base\BaseModel;
use Exception\ServiceException;

/**
 * 实名认证数据库操作类
 */
class CertificationLog extends BaseModel
{

    protected $table = 'user_certification_errlog';
    protected $id = 'id';
    protected $field = array(
            "id",
            "u_id",
            "desc",
            "errormsg",
            "create_time",
        );

    public function getAll( $param ) {
        $sql = "select * from `{$this->table}` where 1 ";
        foreach ($param as $key => $value) {
            if ( !in_array($key, $this->field) ) {
                continue;
            }
            if ( is_array($value) ) {
                $tempstr = implode(',', $value);
                $sql .= " and `$key` in ({$tempstr}) ";
            } else {
                $sql .= " and `$key`='{$value}' ";
            }
        }

        return $this->mysql->select($sql);
    }
}
