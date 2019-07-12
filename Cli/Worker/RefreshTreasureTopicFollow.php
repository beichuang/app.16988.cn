<?php

/**
 * 刷新晒宝话题关注数量
 */
namespace Cli\Worker;

class RefreshTreasureTopicFollow
{

    public function run()
    {
        $userCount=app('mysqlbxd_user')->fetchColumn("select count(*) c from `user`");
        $count=$userCount+154321 ;
        app('mysqlbxd_app')->query("UPDATE `treasure_topic`  SET `tt_followCount`= {$count}  where tt_required=1");
    }
}
