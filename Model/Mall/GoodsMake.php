<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/28
 * Time: 19:53
 */

namespace Model\Mall;
use Lib\Base\BaseModel;

class GoodsMake  extends BaseModel
{
     public  static function insertMakeGoods($data=[])
     {
         $time  = date('Y-m-d H:i:s');
         if (!$data) {
             throw new \Exception\ParamsInvalidException("商品参数有误");
         }
         $insert_sql = "INSERT INTO make_goods (goods_id,goods_explain,money,deliver_time)
                        VALUES ({$data['goods_id']},'{$data['goods_explain']}',{$data['money']},'{$data['deliver_time']}')";
         $affect            = app('mysqlbxd_mall_user')->query($insert_sql);
         return $affect;
     }
}