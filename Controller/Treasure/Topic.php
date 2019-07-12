<?php
/**
 * 话题
 * @author Administrator
 *
 */
namespace Controller\Treasure;

use Exception\ServiceException;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Topic extends BaseController
{

    /**
     *  话题详情
     *
     * @throws ModelException
     */
    public function detail() {
        $tt_no = app('request')->params('tt_no');
        $row=app('mysqlbxd_app')->fetch('select * from treasure_topic where tt_no=:tt_no ',[
            'tt_no'=>$tt_no
        ]);
        if($row){
            $ttModel=new \Model\Treasure\TreasureTopic();
            $follows=$ttModel->getFollowMap(array_column([$row],'tt_no'),$this->uid);
            $row['tt_image']=FileHelper::getFileUrl($row['tt_image']);
            $row['tt_bgimage']=FileHelper::getFileUrl($row['tt_bgimage']);
            $row['isFollow']=$follows[$row['tt_no']];
        }
        $this->responseJSON($row);
    }
    /**
     *  我关注的话题列表
     *
     * @throws ModelException
     */
    public function my() {
        $data=app('mysqlbxd_app')->select("select * from treasure_topic where tt_required=1");
        $followed=[];
        foreach ($data as &$row){
            $row['tt_image']=FileHelper::getFileUrl($row['tt_image']);
            $row['tt_bgimage']=FileHelper::getFileUrl($row['tt_bgimage']);
            $row['isFollow']=1;
            $followed[$row['tt_no']]=$row;
        }
        $list=app('mysqlbxd_app')->select('select * from treasure_topic_follow where u_id=:u_id order by tt_add_time desc limit 100',[
            'u_id'=>$this->uid
        ]);
        if($list){
            $ttnos=array_unique(array_column($list,'tt_no'));
            $topiclist=app('mysqlbxd_app')->select("select * from treasure_topic where tt_required=0 and (tt_no in('".implode("','",$ttnos)."'))");
            foreach ($topiclist as $row){
                if($followed[$row['tt_no']]){
                    continue;
                }
                $row['tt_image']=FileHelper::getFileUrl($row['tt_image']);
                $row['tt_bgimage']=FileHelper::getFileUrl($row['tt_bgimage']);
                $row['isFollow']=1;
                $followed[$row['tt_no']]=$row;
            }
        }
        $this->responseJSON(array_values($followed));
    }
    /**
     *  话题列表
     *
     * @throws ModelException
     */
    public function lists() {
        $list=app('mysqlbxd_app')->select('select * from treasure_topic order by tt_required desc , tt_recommendSort desc limit 100');
        if($list){
            $ttModel=new \Model\Treasure\TreasureTopic();
            $follows=$ttModel->getFollowMap(array_column($list,'tt_no'),$this->uid);
            foreach ($list as &$row){
                $row['tt_image']=FileHelper::getFileUrl($row['tt_image']);
                $row['tt_bgimage']=FileHelper::getFileUrl($row['tt_bgimage']);
                $row['isFollow']=$follows[$row['tt_no']];
            }
        }
        $this->responseJSON($list);
    }

    /**
     *  推荐话题列表
     *
     * @throws ModelException
     */
    public function recommendLists() {
        $list=app('mysqlbxd_app')->select('select * from treasure_topic order by tt_recommendSort desc limit 100');
        if($list){
            $ttModel=new \Model\Treasure\TreasureTopic();
            $follows=$ttModel->getFollowMap(array_column($list,'tt_no'),$this->uid);
            foreach ($list as &$row){
                $row['tt_image']=FileHelper::getFileUrl($row['tt_image']);
                $row['tt_bgimage']=FileHelper::getFileUrl($row['tt_bgimage']);
                $row['isFollow']=$follows[$row['tt_no']];
                $row['tt_followCount'] +=12367;    //查看数量随机    +12367
            }
        }
        $this->responseJSON($list);
    }
    /**
     * 关注、取消关注话题
     *
     * @throws ModelException
     */
    public function follow()
    {
        $u_id = $this->uid;
        $tt_id = app('request')->params('tt_no');
        $option = app('request')->params('option');
        if (! $tt_id) {
            throw new ParamsInvalidException('话题id必须');
        }
        if(!in_array($option,['follow','unfollow'])){
            throw new ParamsInvalidException('option参数错误');
        }
        if(!$topic=app('mysqlbxd_app')->fetch('select * from treasure_topic where tt_no=:tt_no',[
            'tt_no'=>$tt_id
        ])){
            throw new ParamsInvalidException('话题不存在');
        }
        $followData=app('mysqlbxd_app')->fetch('select * from treasure_topic_follow where tt_no=:tt_no and u_id=:u_id',[
            'tt_no'=>$tt_id,
            'u_id'=>$u_id,
        ]);
        $topic['tt_followCount']=($topic['tt_followCount']<=0)?0:$topic['tt_followCount'];
        $time=date('Y-m-d H:i:s');
        if($option=='follow'){
            if($followData){
                throw new ServiceException("关注失败，请稍后重试");
            }else{
                $updateData=[
                    'tt_followCount'=>($topic['tt_followCount']+1),
                    'tt_update_time'=>$time,
                ];
                app('mysqlbxd_app')->update('treasure_topic',$updateData,['tt_no'=>$tt_id]);
                app('mysqlbxd_app')->insert('treasure_topic_follow',[
                    'tt_no'=>$tt_id,
                    'u_id'=>$u_id,
                    'tt_add_time'=>$time,
                ]);
            }
        }else if($option=='unfollow'){
            if($followData){
                $updateData=[
                    'tt_followCount'=>(($topic['tt_followCount']-1)<0)?0:($topic['tt_followCount']-1),
                    'tt_update_time'=>$time,
                ];
                app('mysqlbxd_app')->update('treasure_topic',$updateData,['tt_no'=>$tt_id]);
                app('mysqlbxd_app')->delete('treasure_topic_follow',[
                    'tt_no'=>$tt_id,
                    'u_id'=>$u_id,
                ]);
            }else{
                throw new ServiceException("取消关注失败，请稍后重试");
            }
        }
        $this->responseJSON(true);
    }
}