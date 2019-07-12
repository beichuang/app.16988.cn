<?php
namespace Cli\Worker;

/**
 * 历史圈子话题数据初始化
 * Class TreasureTopicHistoryDataInit
 * @package Cli\Worker
 */

class TreasureTopicHistoryDataInit
{
    /**
     * 入口函数
     */
    public function run()
    {
        $requiredTagList=app('mysqlbxd_app')->select("select tt_no from treasure_topic where tt_required=1");
        if($requiredTagList){
            $tt_nos=array_column($requiredTagList,'tt_no');
            $t_idList=app('mysqlbxd_app')->select("select t_id from treasure");
            var_dump('total:',count($t_idList));
            $totalCount=0;
            $ttModel=new \Model\Treasure\TreasureTopic();
            foreach ($tt_nos as $tt_no){
                foreach ($t_idList as $t_idRow){
                    $t_id=$t_idRow['t_id'];
                    $refCount=app('mysqlbxd_app')->fetchColumn("select count(*) c from treasure_topic_ref where t_id=:t_id and tt_no=:tt_no",['t_id'=>$t_id,'tt_no'=>$tt_no]);
                    if($refCount==0){
                        $count=$ttModel->updateTreasureTopicRef([$tt_no],$t_id);
                        $totalCount+=$count;
                        var_dump("update:{$count}");
                    }
                }
            }
            var_dump("totalCount:{$totalCount}");
        }
        var_dump("finish");
    }
}
