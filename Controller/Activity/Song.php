<?php

// 活动管理

namespace Controller\Activity;
use Exception\ParamsInvalidException;
use Exception\ServiceException;
use Framework\Helper\FileHelper;
use GuzzleHttp\Exception\ServerException;
use Lib\Base\BaseController;

/**
 * 火客之歌（选择喜欢的战队）
 * Class Index
 * @package Controller\Activity\GuestSong
 */
class  Song  extends  BaseController
{
    /**
     * 火客之歌投票时间配置
     */
    public function  songConfig(){
         $time     = app()->request()->params('time','');
         $time     = $time?$time:'';
         $redis    = app('redis');
         if($time){
             $end_time = time()+ $time*60;
         }else{
             $end_time = '';
         }
         $start_time  = $redis->setex('hksong_end_time',100000000000,$end_time);
         return $this->responseJSON(['message'=>'配置传成功']);
    }

    /**
     * 活动初始化  返回服务器倒计时时间 和  会员是否投过票了
     * @throws ServiceException
     */
    public  function initMessage(){
        //1：返回服务器倒计时
        $redis           = app('redis');
        $end_time        = $redis->get('hksong_end_time')?$redis->get('hksong_end_time'):'';
        $count_down_time = (!$end_time)?'':$end_time-time();
        //2: 返回微信号是否投过票
        $openid         = app()->request()->params('openid','');
        $is_vote_sql    = 'SELECT id FROM `activity_song` WHERE  openid =:openid';
        $is_vote_result = app('mysqlbxd_app')->fetch($is_vote_sql, [':openid' => $openid]);
        $is_vote_result = empty($is_vote_result)?false:true;
        $this->responseJSON(['is_vote_result'=>$is_vote_result,'count_down_time'=>$count_down_time]);
    }

    /**
     *  战队投票数量
     */
    public function initActivity(){
         //所有战队的最终投票数量
         $xjf4  = "SELECT count(*) as xjf4 FROM activity_song WHERE  LOCATE('1',team)";
         $xjf4  = app('mysqlbxd_app')->fetch($xjf4);
         $xvsh  = "SELECT count(*) as xvsh FROM activity_song WHERE  LOCATE('2',team)";
         $xvsh  = app('mysqlbxd_app')->fetch($xvsh);
         $xhd   = "SELECT count(*) as xhd FROM activity_song WHERE  LOCATE('3',team)";
         $xhd   = app('mysqlbxd_app')->fetch($xhd);
         $zwtt  = "SELECT count(*) as zwtt FROM activity_song WHERE  LOCATE('4',team)";
         $zwtt  = app('mysqlbxd_app')->fetch($zwtt);
         $plpl  = "SELECT count(*) as plpl FROM activity_song WHERE  LOCATE('5',team)";
         $plpl  = app('mysqlbxd_app')->fetch($plpl);
         $num   = ['xjf4'=>$xjf4['xjf4'],'xvsh'=>$xvsh['xvsh'],'xhd'=>$xhd['xhd'],'zwtt'=>$zwtt['zwtt'],'plpl'=>$plpl['plpl']];
         $this->responseJSON(['num'=>$num]);
    }
    /**
     * 火客之歌开奖选择喜欢的战队
     */
    public function chooseTeam(){
         //判断是否到投票开始时间
         $redis      = app('redis');
         $start_time = $redis->get('hksong_end_time');
         if(!$start_time){
             throw new ServiceException('活动还没开始哦');
         }
         $request = app()->request();
         $team   = $request->params('team','');
         if(!$team){
             throw new ParamsInvalidException('请选择战队');
         }
         $openid = $request->params('openid','');
         if(!$openid){
             throw new ParamsInvalidException('请通过掌玩优选(公众号)参加投票');
         }
         $job_number = $request->params('job_number','');
         if(!$job_number){
             throw new ParamsInvalidException('请填写工号');
         }
         // 1-1：判断工号是否存在
         if(!in_array($job_number,config('JobNumber'))){
             throw new ParamsInvalidException('工号有误');
         }
         //2:战队数据验证
         $job_number_v  =  explode(',',$team);
         $diff_arr  = array_diff([1,2,3,4,5],$job_number_v);
         if(count($job_number_v)!=3 || (count($diff_arr)!=2)){
            throw new ServiceException('只能选择3个不同战队');
         }
         //3： 工号判重  微信号判重
         $job_number_sql = 'SELECT id FROM `activity_song` WHERE job_number =:job_number or openid =:openid';
         $job_number_exist = app('mysqlbxd_app')->fetch($job_number_sql, [':job_number' => $job_number,':openid' => $openid]);
         if($job_number_exist){
              throw new ServiceException('不能重复投票');
         }
         //4: 插入数据
        $insert_data    = [
            'openid'=>$openid,
            'team'  =>$team,
            'job_number'=>$job_number,
            'create_at'=>time()
        ];
         $choose_insert = app('mysqlbxd_app')->insert('activity_song',$insert_data);
         if(!$choose_insert){
             throw  new ServerException('投票失败');
         }
        return $this->responseJSON(['message'=>'投票成功']);
    }
}



