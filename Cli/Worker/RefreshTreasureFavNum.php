<?php

/**
 * 刷新晒宝收藏数量
 */
namespace Cli\Worker;

class RefreshTreasureFavNum
{

    public function run()
    {
        $sql="SELECT ufav_objectKey,count(*) c from user_favorite where ufav_type=5 GROUP BY ufav_objectKey";
        $list=app('mysqlbxd_app')->select($sql);
        app('mysqlbxd_app')->query("UPDATE `treasure`  SET `t_favTimes`= 0 ");
        if($list){
            foreach ($list as $row){
                app('mysqlbxd_app')->update('treasure',[
                    't_favTimes'=>$row['c']
                ],[
                    't_id'=>$row['ufav_objectKey']
                ]);
                echo "t_favTimes:{$row['c']} , t_id:{$row['ufav_objectKey']} \n";
            }
        }
    }
}
