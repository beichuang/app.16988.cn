<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/26
 * Time: 15:48
 */

namespace Model\User;
use Lib\Base\BaseModel;

class UserExtend  extends BaseModel
{
    protected $table = 'user_extend';
    protected $id = 'u_id';

    /**
     *获取相应字段信息
     * @param $field   //条件字段
     * @param $value   //值
     * @param $select  //需要查询获取的字段
     * @throws \Exception\ParamsInvalidException
     */
    public static function getOneColumn($select,$field='',$value=""){
        if (! $field) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $sql = "select {$select} from user_extend where {$field} =:{$field} ";
        $where = [":{$field}"=>$value];
        $data = app('mysqlbxd_user')->fetch($sql, $where);
        return $data;
    }
}