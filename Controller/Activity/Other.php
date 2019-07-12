<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/13
 * Time: 10:08
 */

namespace Controller\Activity;
use Framework\Helper\WxHelper;
use Lib\Base\BaseController;
use Lib\User\UserVoucher;
use Model\Common\Activity;
use Rest\User\Facade\SmsManager;

class Other  extends BaseController
{
    // 1=>100元优惠券（满599-100） 5%
    // 2=>50元优惠券（满499-50）   25%
    // 3=>30元优惠券（满399-30）   30%
    // 4=>5元优惠券（满109-5）     30%
    // 5=>手机支架1个              5%
    // 6=>谢谢惠顾                 5%
    private $prize            = ['2019520-599-100'=>[1,50000],'2019520-499-50'=>[50001,300000],'2019520-399-30'=>[300001,600000],'2019520-109-5'=>[600001,935000],'sjzj'=>[935001,950000],'xxhg'=>[950001,1000000]];
    //活动主页面地址
    const MAIN_PAGE = '/html/apph5/drawingWriting.html#/index';
    //活动详情页地址
    const DETAIL_PAGE = '/html/apph5/drawingWriting.html#/detail';
     /**
      *0元活动随机抽奖  (2019520随机抽奖)
      */
    public function  LuckDraw2019520(){
            //判断抽奖次数

            $sendResult ="";
            $count                   = UserVoucher::LuckDraw2019520Limit($this->uid);
            if($count>=3){
                throw new \Exception\ParamsInvalidException("每天只能抽3次奖哦！");
            }

            $kResult           = '';
            $LuckDrawValue     = mt_rand(1,1000000);
            foreach($this->prize as $k=>$v){
                   if(($LuckDrawValue>=$v[0])&&($LuckDrawValue<=$v[1])){
                       $kResult =  $k;
                       break;
                   }
            }
           //代金券 数据库录入
           if($kResult=='sjzj'){
                $prize_type =  2;
                $prize_name = '手机支架';
           }elseif ($kResult=='xxhg'){
                $prize_type =  3;
                $prize_name = "谢谢惠顾";
           }else{
                $prize_type =  1;
                $prize_name =  $kResult;
                //执行发券逻辑
                $sendResult   = $this->sendCoupon2019520($this->uid,$kResult);
           }
           $insert = app('mysqlbxd_app')->insert('luck_draw_20190520_log', ['prize_type'=>$prize_type,'prize_name'=>$prize_name,'uid'=>$this->uid]);
           $this->responseJSON(['prizeName'=>$kResult,'prizeId'=>$insert[1],'send_result'=>$sendResult]);
    }
    /**
     * @param string $uid
     * @param string $voucher
     * @return mixed
     */
    private function sendCoupon2019520($uid='',$voucher=''){
        $send =  UserVoucher::sendVoucherLuckDraw2019520($uid,$voucher,$start='2019520-luck-draw-start',$end="2019520-luck-draw-end");
        return $send["sendSuccess"];
    }
    /**
     * 用户提交中奖纪录
     */
    public function  awardMessage(){
        $activity_id     = app()->request()->params('id','');
        $name            = app()->request()->params('name','');
        $phone           = app()->request()->params('phone','');
        $address         = app()->request()->params('address','');
        if(!$activity_id||!$name||!$phone||!$address){
            throw new \Exception\ParamsInvalidException("参数不能为空！");
        }
        $update = app('mysqlbxd_app')->update('luck_draw_20190520_log',['name'=>$name,"phone"=>$phone,'address'=>$address],["activity_id"=>$activity_id]);
        if($update){
            $this->responseJSON(["success"=>'提交成功']);
        }else{
            throw new \Exception\ParamsInvalidException("您已经提交过了！");
        }
    }


    //-----------------书法报名是否参加活动  start-------------------
    public function  isHaveJoin(){
            $phone   = app()->request()->params('phone','');
            $user    =   Activity::isHaveJoin($phone);
            $endData = ['type'=>$user?1:0];
            $this->responseJSON($endData);
    }
    //-----------------书法报名是否参加活动   end-------------------


    //--------------------书画艺术大赛报名start   -------------------------
    /**
     * 比赛投票
     * @throws \Exception\ParamsInvalidException
     */
    public function worksVote()
    {
        $id = app()->request()->params('workId', '');
        if (!$id) {
            throw new \Exception\ParamsInvalidException("作品id不存在!");
        }
        //1:比赛状态限制
        $config_handwriting_activity_vote = Activity::activity_config();
        $vote_status = time_status($config_handwriting_activity_vote['handwriting_activity_vote']);
        if ($vote_status == 0) {
            throw new \Exception\ParamsInvalidException('投票活动未开启');
        }
        if ($vote_status == 2) {
            throw new \Exception\ParamsInvalidException('投票已结束');
        }
        //2:活动第一次投票送优惠券
        $vote_first_sql = "select  id  from  activity_handwriting_vote_record where  uid={$this->uid}";
        $vote_first = app('mysqlbxd_app')->fetch($vote_first_sql);
        $redis = app('redis');
        if (!$redis->get('handwriting_activity_template_id')) {
            $templateIds = app('mysqlbxd_mall_common')->fetchColumn("select svalue from setting where skey='handwriting_activity_template_id'");
            $templateIds = explode(',', $templateIds);
            $redis->set('handwriting_activity_template_id', json_encode($templateIds));
        } else {
            $templateIds = json_decode($redis->get('handwriting_activity_template_id'), true);
        }
        $user_voucher = app("mysqlbxd_mall_user")->fetchColumn("select  v_id  from  voucher  where  u_id={$this->uid} and v_t_id={$templateIds[0]}");
        if (!$vote_first && !$user_voucher) {
            $send_result = UserVoucher::sendVoucherPublic($this->uid, [$templateIds[0]], 'one');
        }
        //3：每人每天最多投1票，大于1票，检查是否已经关注，没有关注,强制去关注,关注成功继续送2票
        $insert = false;
        try {
            //开启事务
            app('mysqlbxd_app')->beginTransaction();
            app('mysqlbxd_mall_user')->beginTransaction();
            $start_limit = date("Y-m-d") . " 00:00:00";
            $end_limit = date("Y-m-d") . " 23:59:59";
            $count = app('mysqlbxd_app')->fetchColumn("select count(*) as num from  activity_handwriting_vote_record where uid={$this->uid} and ( create_time  BETWEEN    '{$start_limit}' AND   '{$end_limit}' ) ");
            if ($count >= 3) throw new \Exception\ParamsInvalidException('每天最多可以投3次票');

            //第2~3次投票
            if ($count >= 1) {

                //判断是否关注
                $follow = app("mysqlbxd_app")->fetchColumn("select id  from handwriting_activity_user_follow where uid={$this->uid} and is_follow=1");
                if (!$follow) {
                    //再次判断是否关注
                    //$openId = WxHelper::getOpenId();
                    $openId = WxHelper::getCookieOpenId();
                    wlog($openId, 'sss', 4);
                    if ($openId) {

                        $return = WxHelper::getUserInfo(WxHelper::getAccessToken(), $openId);
                        if (isset($return['subscribe']) && ($return['subscribe'] == 1)) {
                            //增加关注记录
                            app("mysqlbxd_app")->insert("handwriting_activity_user_follow", ['uid' => $this->uid, 'is_follow' => 1]);
                        } else {
                            return $this->responseJSON('', 0, -2, '关注微信公众号');
                            //throw new \Exception\ParamsInvalidException('关注微信公众号,提升至每天投票3次');
                        }

                    } else {
                        $redirectUrl = 'https://app.16988.cn/html/apph5/drawingWriting.html#/detail?id=' . $id;
                        $url = WxHelper::getWxAuthUrl($redirectUrl);
                        $data = [
                            'url' => $url,
                            'vote_number' => $count
                        ];
                        return $this->responseJSON($data, 0, -1, '请授权登陆');
                        //throw new \Exception\ParamsInvalidException('打开微信客户端,关注公众号,每天投票3次');
                    }
                }

            }

            $sql = "update  activity_handwriting_match_enroll set vote_number = vote_number+1 where id=:id ";
            $addVoteNum = app("mysqlbxd_app")->query($sql, ['id' => $id]);
            if (!$addVoteNum) {
                throw new \Exception("投票失败,请重试");
            }
            $params['uid'] = $this->uid;
            $params['work_id'] = $id;
            //增加投票记录
            $insert = app("mysqlbxd_app")->insert("activity_handwriting_vote_record", $params);
            if (!$insert) {
                throw new \Exception("投票失败,请重试");
            }
            //投票满3次  发送优惠券
            if ($count + 1 == 3) {
                $send_result = UserVoucher::sendVoucherPublic($this->uid, [$templateIds[1]], 'one');
            }
            app('mysqlbxd_app')->commit();
            app('mysqlbxd_mall_user')->commit();


        } catch (\Exception $e) {
            //事务回滚
            app('mysqlbxd_app')->rollback();
            app('mysqlbxd_mall_user')->rollback();
            throw new \Exception\ParamsInvalidException($e->getMessage());
        }
        //投票总数修正
        if ($insert) {
            //投票总数
            $redis = app('redis');
            $redis->incrBy('handwriting_activity_vote_num', 1);
            $this->responseJSON(['status' => 0, 'message' => '投票成功','vote_number'=>$count+1]);
        } else {
            $this->responseJSON(['status' => 1, 'message' => '投票失败']);
        }
    }
    /**
     * 首页相关数据采集
     * @throws \Exception\ParamsInvalidException
     */
     public function  homePage(){
         $redis    = app('redis');
         //已报名人数
         $config_handwriting_activity_vote['join_number']   = app('mysqlbxd_app')->fetchColumn("select count(*) as  join_number from activity_handwriting_match_enroll where apply=1");
         $config_handwriting_activity_vote['join_number']   +=$redis->get('handwriting_sing_up_num')?:0;
         // 投票人数
         $config_handwriting_activity_vote['vote_number']  = $redis->get('handwriting_activity_vote_num')?:0;
         //浏览人数
         $config_handwriting_activity_vote['browse_num']    = $redis->get('config_handwriting_activity_browse_num')?:0;
         //配置
         $this->responseJSON($config_handwriting_activity_vote);
     }


    /**
     *浏览量增加  单独的增加
     */
     public function  browseNum(){
         $redis    = app('redis');
         $redis->incrBy('config_handwriting_activity_browse_num',1);
     }

     /**
     * 活动信息配置获取
     */
     public function  activityConfig(){
         $config_handwriting_activity_vote = Activity::activity_config();
         $config_handwriting_activity_vote['surplus_time'] = strtotime(explode(',',$config_handwriting_activity_vote['handwriting_activity_vote'])[1]);
         //状态修正
         if($config_handwriting_activity_vote){
             // 报名状态
             $config_handwriting_activity_vote['handwriting_activity_sing_up_status'] = time_status($config_handwriting_activity_vote['handwriting_activity_sing_up']);
             //  投票状态
             $config_handwriting_activity_vote['handwriting_activity_vote_status']     = time_status($config_handwriting_activity_vote['handwriting_activity_vote']);
             //  评审状态
             $config_handwriting_activity_vote['handwriting_activity_review_status']   = time_status($config_handwriting_activity_vote['handwriting_activity_review']);
             unset($config_handwriting_activity_vote['handwriting_activity_sing_up']);
             unset($config_handwriting_activity_vote['handwriting_activity_vote']);
             unset($config_handwriting_activity_vote['handwriting_activity_review']);
         }
         $this->responseJSON($config_handwriting_activity_vote);
     }

    /**
     * 活动 缓存清空
     */
     public function cacheClear(){
         $redis    = app('redis');
         $result   = $redis->del("config_handwriting_activity_vote");
         $result1  = $redis->del("handwriting_activity_template_id");
         if($result){
             $this->responseJSON('清除成功');
         }else{
             $this->responseJSON('已经清除过了');
         }
     }



    /**
     * 作品展示区
     * @throws \Exception\ParamsInvalidException
     */
     public function  worksList(){
          //作品列表页
         $sql                   = "select id,`type`,`name`,age_section,work_name,vote_number,
                                   is_sell,hand_works,works,prize_type,score,prize_type from 
                                   activity_handwriting_match_enroll where `apply`=1 ";
         $params                = app()->request()->params();
         $params['page']       = array_key_check($params,'page')?$params['page']-1:0;
         $params['pageSize']   = array_key_check($params,'pageSize')?$params['pageSize']:10;
         $where                 = [];
         // 作品id
         if(array_key_check($params,'id')){
             $sql  .= "  and  id=:id";
             $where["id"] = $params['id'];
         }
         //作品名称
         if(array_key_check($params,'work_name')){
             $sql  .= "  and  work_name   like  '%{$params['work_name']}%' ";
             $where["type"] = $params['type'];
         }
         //模糊搜索
         if(array_key_check($params,'search')){
             $params['search'] = trim($params['search']);
             if(is_numeric($params['search'])){
                 $sql  .= "  and (id = {$params['search']}  or  work_name like '%{$params['search']}%') ";
             }else{
                 $sql  .= "  and  work_name like '%{$params['search']}%'  ";
             }
         }
         // 作品类型    1:书法 2:绘画 3手工艺品
         if(array_key_check($params,'type')){
             $sql  .= "  and type =:type";
             $where["type"] = $params['type'];
         }
         // 年龄阶段    1:少儿组（6-12） 2:少年组（13-18） 3：成年组（19岁及以上）
         if(array_key_check($params,'age_section')){
             $sql  .= "  and age_section =:age_section";
             $where["age_section"] = $params['age_section'];
         }

         //获奖类别   0:没有获奖 1:金奖 2:银奖 3:铜奖 4:人气
         if(isset($params['prize_type'])&&in_array($params['prize_type'],[0,1,2,3,4])){
             $sql  .= "  and prize_type =:prize_type";
             $where["prize_type"] = $params['prize_type'];
         }

         //获奖 按照分数排序
         if(in_array($params['prize_type'],[1,2,3,4])){
             $sql  .= "  order by  score  desc   ";
         }else{
             $sql  .= "  order by  create_time  DESC  ";
         }
         //分页
         $limit_start  = $params['page']*$params['pageSize'];
         $sql   .= "   limit {$limit_start},{$params['pageSize']} ";
         $data  =  app('mysqlbxd_app')->select($sql,$where);
         $dataS  = array_map(function($v){
              $array_result    =  json_decode($v['works'],true);
              if(is_string($array_result)){
                  $string_result    =  trim($array_result,"[,],' '");
                  $array_result     =  explode(',',$string_result);
              }
              $v['works'] = $v['works']?$array_result[0]:"";
              return $v;
         },$data);
         $this->responseJSON($dataS);
     }

     //作品求购
     public function  buyWorks(){
         $params               = app()->request()->params();
         //作品名称
         if(!array_key_check($params,'works_id')){
             throw new \Exception\ParamsInvalidException("作品works_id不存在");
         }
         if(!array_key_check($params,'name')){
             throw new \Exception\ParamsInvalidException("姓名不能为空");
         }
         if(!array_key_check($params,'phone')){
             throw new \Exception\ParamsInvalidException("联系人手机号不能为空");
         }
         if(!array_key_check($params,'buy_num')){
             throw new \Exception\ParamsInvalidException("购买数量");
         }
          $params['uid'] = $this->uid;
          app("mysqlbxd_app")->insert('activity_handwriting_works_buy',$params);
          $this->responseJSON('提交成功');
     }


      //作品详情
      public function  worksDetail(){
          $redis = app('redis');
          $redis->incrBy('config_handwriting_activity_browse_num',1);
          $id               = app()->request()->params('id','');
          if(!$id){
              throw new \Exception\ParamsInvalidException("作品id不存在");
          }
          $sql           = "select id,`type`,`name`,age_section,work_name,vote_number,
                            is_sell,hand_works,works,prize_type,score,prize_type from 
                            activity_handwriting_match_enroll where `apply`=1  and id={$id} ";
          $data          =  app("mysqlbxd_app")->fetch($sql);
          //作品数据列表（手持作品+作品展示列表）
          $array_result  =  json_decode($data['works'],true);
          if(is_string($array_result)){
              $string_result    =  trim($array_result,"[,],' '");
              $array_result     =  explode(',',$string_result);
          }
          $array_result  = array_merge([$data['hand_works']],$array_result);
          $data['allWorks'] = $array_result;
          unset($data['hand_works']); unset($data['works']);
          //获取排名
          if($data['type']==3){    //手工艺
              $rank_sql       = "select id from activity_handwriting_match_enroll 
                                 where  vote_number>={$data['vote_number']} and apply=1  ORDER BY vote_number desc,update_time DESC  ";
          }else{
              $rank_sql       = "select id from activity_handwriting_match_enroll 
                                 where  vote_number>={$data['vote_number']} and  apply=1 AND `type`={$data['type']}    ORDER BY vote_number desc,update_time DESC ";
          }
          $rank_data          =  app("mysqlbxd_app")->select($rank_sql);
          $rank_data          =  array_column($rank_data,'id');
          $rank               =  array_search(2,$rank_data)+1;
          $data['rank']       =  $rank;
          $this->responseJSON($data);
      }

    /**
     * 掌玩艺术活动    投票记录
     */
      public function voteRecord(){
          $page               = app()->request()->params("page",1);
          $pageSize           = app()->request()->params("pageSize",10);
          $limitSql           = "limit ".($page-1)*$pageSize.",{$pageSize} ";
          //获取投票配置状态  判断当前的投票状态
          $config_handwriting_activity_vote = Activity::activity_config();
          $config_handwriting_activity_vote_status      = time_status($config_handwriting_activity_vote['handwriting_activity_vote']);
          //修正   我的投票记录
          $sql =  "select work_id,count(*) as vote_num,max(ar.create_time) as create_time,({$config_handwriting_activity_vote_status}) as status,ae.works   from  activity_handwriting_vote_record ar LEFT JOIN activity_handwriting_match_enroll ae ON  ar.work_id = ae.id
                   where ar.uid={$this->uid}  GROUP BY  work_id  ORDER BY  ar.create_time DESC  {$limitSql}";
          $data = app('mysqlbxd_app')->select($sql);
          //主图
          if($data){
              foreach($data as $k=>&$v){
                  $array_result   = json_to_array($v['works']);
                  $v['work_pic'] = $array_result?trim($array_result[0],'"'):'';
                  unset($v['works']);
              }
          }
          $this->responseJSON($data);
      }

    /**
     * 艺术活动对外分享
    */
    public function detailPage()
    {
        $id = app()->request()->params('id');
        if (empty($id)) {
            app()->redirect(self::MAIN_PAGE);
            return;
        }
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            //增加活动访问量
            app()->redirect(self::DETAIL_PAGE . '?id=' . $id);
        }
    }


    /**
     * 艺术活动对外分享
     */
    public function mainPage()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            //增加活动访问量
            app()->redirect(self::MAIN_PAGE);
        }
    }


    /**
     * 奖项列表页一次展示
     */
    public function awardWork(){
        $age_section      = app()->request()->params('age_section','');
        $type             = app()->request()->params('type','');
        $where            = "   `apply`=1 AND  `prize_type` not in (0,4) ";
        if($age_section){
             $where  .= " and  `age_section`={$age_section}";
        }
        if($type){
             $where  .= " and  `type`={$type} ";
        }
        $end_data              = [];
        $sql                   = "select id,`type`,`name`,age_section,work_name,vote_number,
                                   is_sell,hand_works,works,prize_type,score,prize_type from 
                                   activity_handwriting_match_enroll  where  {$where}  ORDER BY  prize_type ASC,id DESC ";
        $data   = app('mysqlbxd_app')->select($sql);
        $data  = array_map(function($v){
            $array_result      =  json_to_array($v['works']);
            $v['works'] = $v['works']?$array_result[0]:"";
            return $v;
        },$data);
        if($data){
            foreach ($data as $k=>$v){
                if($v['prize_type']==1){
                   $jprize[]  =    $v;
                }
                if($v['prize_type']==2){
                  $yprize[]   =    $v;
                }
                if($v['prize_type']==3){
                  $tprize[]    =    $v;
                }
            }
             $end_data[] = isset($jprize)?$jprize:[];
             $end_data[] = isset($yprize)?$yprize:[] ;
             $end_data[] = isset($tprize)?$tprize:[];
        }
        $this->responseJSON($end_data);
    }

    /**
     * 前10名   人气奖   投票名单
     */
    public  function  voteNumberTen(){
        $sql                   =  "select id,`type`,`name`,age_section,work_name,vote_number,
                                   is_sell,hand_works,works,prize_type,score,prize_type from 
                                   activity_handwriting_match_enroll  where `apply`=1   ORDER BY vote_number desc,id ASC LIMIT 0,10 " ;
        $data   = app('mysqlbxd_app')->select($sql);
        $data  = array_map(function($v){
            $array_result    =  json_decode($v['works'],true);
            if(is_string($array_result)){
                $string_result    =  trim($array_result,"[,],' '");
                $array_result     =  explode(',',$string_result);
            }
            $v['works'] = $v['works']?$array_result[0]:"";
            return $v;
        },$data);
        $this->responseJSON($data);
    }

    /**
     *    相关比赛设置
     */
     public function  matchTimeSet(){
         //清除缓存
         $params      = app()->request()->params();
         $vote_num    = app()->request()->params('vote_num',150);
         $browse_num  = app()->request()->params('browse_num',200);
         $sing_up_num = app()->request()->params('sing_up_num',32);
         $redis    = app('redis');
         $redis->del("config_handwriting_activity_vote");
         //1：投票时间设置
         foreach($params as $k=>$v){
                 if(in_array($k,['handwriting_activity_sing_up','handwriting_activity_vote','handwriting_activity_review'])) {
                     $update_sql = " update  setting  set  svalue='{$v}' where skey='{$k}' ";
                     app("mysqlbxd_mall_common")->query($update_sql);
                 }
          }
         //清除缓存
         $redis->del("config_handwriting_activity_vote");
         //重新获取值
         $config_handwriting_activity_vote = Activity::activity_config();
         //2:
         // 初始报名人数
         $redis->set('handwriting_sing_up_num',$sing_up_num);
         // 初始 投票人数
         $redis->set('handwriting_activity_vote_num',$vote_num);
         // 初始 浏览人数
         $redis->set('config_handwriting_activity_browse_num',$browse_num);
         $this->responseJSON($config_handwriting_activity_vote);
     }







    /**
     * 指定用户艺术作品审核
     */
     public function userWorks(){
         
           $workSql   = "select `id` as work_id,`type`,`type`,work_name,age_section,works,phone,is_sell,apply,score,create_time from
                         activity_handwriting_match_enroll  where uid={$this->uid} ORDER BY field(apply,1,2,0) asc, create_time ASC ";
           $data   = app('mysqlbxd_app')->select($workSql);
           $data  = array_map(function($v){
             $array_result    =  json_decode($v['works'],true);
             if(is_string($array_result)){
                 $string_result    =  trim($array_result,"[,],' '");
                 $array_result     =  explode(',',$string_result);
             }
             $v['works'] = $v['works']?$array_result[0]:"";
             return $v;
          },$data);
         $this->responseJSON($data);
     }
    //--------------------书画艺术大赛报名end  -------------------------



    /**
     * 艺术大赛临时发短信
     */
    public function activity_art_send_cms()
    {
        $phone_list = app()->request()->params("phone");
        if (!$phone_list) {
            throw new \Exception\ParamsInvalidException("手机号必须");
        }
        $params = [
            'product' => '掌玩',
        ];

        $arrPhone = explode(',', $phone_list);
//        var_dump($arrPhone);die;
        if ($arrPhone) {
            foreach ($arrPhone as $k => $phone) {
                $result = SmsManager::sendSms('BXD_BASE_VALIDATE', $phone, $params, '掌玩', 'ali', 11);
                var_dump($phone, $result);
            }
        }


    }

    /**
     * 艺术大赛投票统计
     */
    public function everyDayVoteNum()
    {
        $sql = "select DATE_FORMAT(create_time,'%Y%m%d') as times, count(*) as count from activity_handwriting_vote_record group by times";
        $res = app('mysqlbxd_app')->select($sql);
        echo '<pre>';
        print_r($res);
    }

    /**
     * 1500奖品领取
     */
    public function award_receive()
    {
        $message = '请登录';
        $error_type = $error_code = 0;
        $data = [];
        $uId = $this->uid;
        if ($uId) {
            $data = ['u_id' => $uId];
            //是否领取过
            $sql = 'select * from award_receive where u_id = :u_id';
            $bind = [':u_id' => $uId];
            $isExists = app('mysqlbxd_app')->select($sql, $bind);

            if ($isExists) {
                $message = '您已经领取过';
                $error_type = $error_code = 2;

            } else {
                $result = app('mysqlbxd_app')->insert('award_receive', $data);
                if (isset($result[1]) && $result[1]) {
                    $message = '领取成功';
                    $error_type = $error_code = 1;
                } else {
                    $message = '领取失败';
                    $error_type = $error_code = 3;
                }
            }

        }
        return $this->responseJSON($data, $error_type, $error_code, $message);

    }




 







}