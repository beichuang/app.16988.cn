<?php

/**
 * 刷新用户作品数量
 */
namespace Cli\Worker;

class RefreshUsersGoodsNum
{

    public function run()
    {
        $sql="select g_salesId,count(*) c from goods where g_status=3 and g_stock>0 and g_type=1 group by g_salesId";
        $list=app('mysqlbxd_mall_user')->select($sql);
        app('mysqlbxd_user')->query("UPDATE `user_extend`  SET `ue_goodsNum`= 0 ");
        if($list){
            foreach ($list as $row){
                app('mysqlbxd_user')->update('user_extend',[
                    'ue_goodsNum'=>$row['c']
                ],[
                    'u_id'=>$row['g_salesId']
                ]);
                echo "ue_goodsNum:{$row['c']} , u_id:{$row['g_salesId']} \n";
            }
        }
    }
}
