<?php

namespace Model\Treasure;

use Framework\Helper\FileHelper;

class TreasureTopic{

    /**
     * 获取必须的话题id数组
     * @return array
     */
   public function getRequiredTopicIds()
   {
       if($list=app('mysqlbxd_app')->select('select tt_no from treasure_topic where tt_required=1')){
           return array_column($list,'tt_no');
       }
       return [];
   }

    /**
     * 增加/更新晒宝和话题的关联
     * @param $topicIds
     * @param $t_id
     * @return int
     */
   public function updateTreasureTopicRef($topicIds,$t_id){
       if($topicIds && is_array($topicIds)){
           $topicIds=array_unique(array_filter($topicIds));
           if($topicIds){
               $this->validateTopicNos($topicIds);
               //先移除历史关联
               $this->removeTreasureTopicRef($t_id);
               $time=date('Y-m-d H:i:s');
               foreach ($topicIds as $tt_no){
                   app('mysqlbxd_app')->insert('treasure_topic_ref',[
                       'tt_no'=>$tt_no,
                       't_id'=>$t_id,
                       'add_time'=>$time,
                   ]);
               }
               app('mysqlbxd_app')->query("update treasure_topic set tt_contentCount=tt_contentCount+1 where tt_no in ('".implode("','",$topicIds)."') ");
               return app('mysqlbxd_app')->getEffectRowCount();
           }
       }
       return 0;
   }
    /**
     * 移除晒宝和话题的关联
     * @param $topicIds
     * @param $t_id
     * @return int
     */
   public function removeTreasureTopicRef($t_id,$topicIds=[]){
       if(!$topicIds){
           $topicIdsList=app('mysqlbxd_app')->select('select tt_no from treasure_topic_ref where t_id=:t_id',[
               't_id'=>$t_id
           ]);
           if($topicIdsList){
               $topicIds=array_column($topicIdsList,'tt_no');
           }
       }
       if($topicIds){
           $this->validateTopicNos($topicIds);
           app('mysqlbxd_app')->query("delete from treasure_topic_ref where t_id=:t_id and tt_no in('".implode("','",$topicIds)."')",[
               't_id'=>$t_id,
           ]);
           app('mysqlbxd_app')->query("update treasure_topic set tt_contentCount=tt_contentCount-1 where tt_no in ('".implode("','",$topicIds)."') ");
           return app('mysqlbxd_app')->getEffectRowCount();
       }
       return 0;
   }

    /**
     * @param  array $tt_nos
     * @return bool
     * @throws \Exception\ParamsInvalidException
     */
   private function validateTopicNos($tt_nos){
       if($tt_nos){
           $str=implode(',',$tt_nos);
           if(!preg_match("/^([a-zA-Z0-9_-],?)+$/",$str)){
               throw new \Exception\ParamsInvalidException("话题参数格式错误");
           }
       }
       return true;
   }
    /**
     * @param  array $tt_nos
     * @return bool
     * @throws \Exception\ParamsInvalidException
     */
   public function isTopicNos($tt_nos){
       if($tt_nos){
           $this->validateTopicNos($tt_nos);
           $ttnoList=app('mysqlbxd_app')->select("select tt_no from  treasure_topic where tt_no in ('".implode("','",$tt_nos)."') ");
           $ttnoList=$ttnoList?$ttnoList:[];
           if($diff=array_diff($tt_nos,array_column($ttnoList,'tt_no'))){
               throw new \Exception\ParamsInvalidException("话题编号".implode(',',$diff)."不存在");
           }
       }
       return true;
   }
   public function getTreasureRefTopics($t_id)
   {
       $tt_noList=app('mysqlbxd_app')->select("select tt_no from  treasure_topic_ref where t_id=:t_id",[
           't_id'=>$t_id
       ]);
       if(!$tt_noList){
           return [];
       }
       $tt_nos=array_column($tt_noList,'tt_no');
       $tList=app('mysqlbxd_app')->select("select tt_no,tt_name from  treasure_topic where tt_no in ('".implode("','",$tt_nos)."') ");
       return $tList;
   }



    /**
     * 获取关注数据
     * @param $tt_nos
     * @param $u_id
     */
    public function getFollowMap($tt_nos,$u_id)
    {
        $res=[];
        $followNos=[];
        if($tt_nos && is_array($tt_nos) && $u_id){
            $str=implode("','",$tt_nos);
            $t_nosFollowList=app('mysqlbxd_app')->select("select tt_no from treasure_topic_follow where u_id=:u_id and tt_no in('".$str."')",[
                'u_id'=>$u_id
            ]);
            if($t_nosFollowList){
                $followNos=array_column($t_nosFollowList,'tt_no');
            }
        }
        foreach ($tt_nos as $t_no){
            if($followNos && in_array($t_no,$followNos)){
                $res[$t_no]=1;
            }else{
                $res[$t_no]=0;
            }
        }
        return $res;
    }
}
