<?php
/**
 * 签到
 * @author Administrator
 *
 */
namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ModelException;
use Exception\ServiceException;


class Sign extends BaseController
{

    private $inviteModel = null;

    private $integralService = null;

    protected $userApi = null;

    public function __construct()
    {
        parent::__construct();
        $this->userApi = get_api_client('User');
        $this->integralService = new \Lib\User\UserIntegral();
    }

    /**
     * 签到
     *
     * @throws ModelException
     */
    public function add()
    {
        $isSigned = false;
        $uid = $this->uid;
        if(!$currentIntegral=$this->integralService->addIntegral($uid,\Lib\User\UserIntegral::ACTIVITY_SIGNIN_ADD)){
            //獲取目前積分
            $newLib = new \Lib\User\User();
            $userInfo = $newLib->getUserInfo(array($uid));
            if ($userInfo){
                $currentIntegral = $userInfo[$uid]['u_integral'];
            }
            $isSigned = true;
        }

        list($todayIntegral,$signInTimes,$day7Integral)=$this->integralService->getSignInIntegralInfo($uid);
        $daysToday=1;
        if($regDate=app('mysqlbxd_user')->fetchColumn('select u_createDate from `user` where u_id=:u_id',[
            'u_id'=>$uid
        ])){
            $seconds=time()-strtotime($regDate);
            $daysToday=$daysToday+(int)($seconds/86400);
        }

        //今天签到得多少积分
        //今天是第几天
        //7天签到得多少积分
        $this->responseJSON(
            array(
                'currentIntegral' => $currentIntegral?$currentIntegral:0,
                'daysToday'=>$daysToday,
                'integralToday'=>$todayIntegral,
                'integral7Day'=>$day7Integral,
                'isSigned'=> $isSigned
            ));
    }

    /**
     * 查询今天签到的次数
     *
     * @param int $uid            
     * @return number
     */
    private function getTodayInviteTime($uid)
    {
        //type=3 签到
        $logs = $this->integralService->getHistoryLogs($uid, 3, 1, 1, '', '');

        if (isset($logs['totalCount'])) {  //有签到记录
            $lasted = $logs['rows'][0];
            $time = $lasted['uil_createDate'];

            //今日已签到
            if((date('Y-m-d 00:00:00') < $time) && ($time < date('Y-m-d 23:59:59'))){
                return ['count'=>1];
            }else if ((date("Y-m-d 00:00:00",strtotime("-1 day")) < $time) && ($time < date('Y-m-d 00:00:00'))){//签到记录为昨天的
                return ['count'=>0,'isContinue'=>1];
            }else {
                return ['count'=>0,'isContinue'=>0];
            }
        }

        return ['count'=>0];
    }

    /**
     * 签到记录
     */
    public function lists(){
        $uid = $this->uid;

        //获取当前周的日期
        $time = time();
        $whichD = date('w',strtotime($time));
        $weeks = array();
        for($i=0;$i<7;$i++){
            if($i<$whichD){
                $date = $time-($whichD-$i)*24*3600;
            }else{
                $date = $time+($i-$whichD)*24*3600;
            }
            $weeks[$i] = date('Y-m-d',$date);

        }

        foreach($weeks as $day){
            $data['day'] = date('m.d',strtotime($day));
            $data['isSign'] = 0;
            $startTime = $day.' 00:00:00';
            $endTime = $day.' 23:59:59';
            $logs = $this->integralService->getHistoryLogs($uid, 3, 1, 1, $startTime, $endTime);
            if($logs['totalCount']){
                $data['isSign'] = 1;
            }
            $return[] = $data;
        }

        $this->responseJSON($return);
    }
}
