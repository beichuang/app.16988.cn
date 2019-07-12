<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/30
 * Time: 10:52
 */

namespace Model\Common;
use Exception\ServiceException;

class Activity
{
    //---------------------------------------书法活动--------------------------
     //书法节活动报名
     public static  function handwritingSign($insert_data=[]){
          //查询是否报过名
          if(!$insert_data['phone'] || !$insert_data['name']){
              throw new ServiceException('电话或姓名不能为空');
          }
          $user   = app("mysqlbxd_app")->fetch("select  `phone`  from  activity_handwriting_sign where phone=:phone",['phone'=>$insert_data['phone']]);
          if($user){
              throw new ServiceException('您已经报过名了！');
          }
          app("mysqlbxd_app")->insert('activity_handwriting_sign',$insert_data);
     }
     //是否参加过活动
    public static function isHaveJoin($phone){
        $user   = app("mysqlbxd_app")->fetch("select  `phone`  from  activity_handwriting_sign where phone=:phone",['phone'=>$phone]);
        return  $user;
    }
    //---------------------------------------书法活动end--------------------------
    //---------------------------------------书画艺术大赛报名(2019-06-04) start--------------------------
    /**
     * @param $insertData
     * @throws ServiceException
     * 书法比赛报名
     */
       public static function  handwriting_match_enroll($insertData=[]){
            //用户uid判断
            if(!array_key_check($insertData,'u_id')){
               throw new ServiceException('用户uid不能为空！');
            }
            $params['uid'] = $insertData['u_id'];
            //1：作品类型
            if(!array_key_check($insertData,'type')||!in_array($insertData['type'],[1,2,3])){
                throw new ServiceException('作品类型有误！');
            }
            $params['type'] = $insertData['type'];
            //2：选择年龄段
            if($params['type']!=3){          //除了手工艺   年龄断增加验证
                if(!array_key_check($insertData,'age_section')||!in_array($insertData['age_section'],[1,2,3])){
                    throw new ServiceException('请选择年龄区间段！');
                }
                $params['age_section'] = $insertData['age_section'];
            }else{   //等于3不区分年龄段
                $params['age_section'] = 0;
            }
            //3：姓名
            if(!array_key_check($insertData,'name')){
                throw new ServiceException('姓名不能为空！');
            }
            $params['name'] = $insertData['name'];
            //3-1   作品名称
            if(!array_key_check($insertData,'work_name')){
                throw new ServiceException('作品名称不能为空！');
            }
            $params['work_name'] = $insertData['work_name'];
            // 4：年龄
            if(!array_key_check($insertData,'age')){
                throw new ServiceException('年龄不能为空！');
            }
            $params['age'] = $insertData['age'];
            //5手持作品照片
            if(!array_key_check($insertData,"hand_works")){
                   throw new ServiceException('手持作品不存在！');
            }
            $params['hand_works'] = $insertData['hand_works'];
            //6：作品照片
            if(!array_key_check($insertData,"works")){
                   throw new ServiceException('手持作品不存在！');
            }
            $params['works']    = $insertData['works'];
            //7：联系人手机号
            if(!array_key_check($insertData,"phone")){
                   throw new ServiceException('手机号不存在！');
            }
            $params['phone']= $insertData['phone'];
            //8:参赛作品书否同意出售
            if(!array_key_check($insertData,'is_sell','exist')&&in_array($insertData['is_sell'],[0,1])){
                throw new ServiceException('请选择是否出售！');
            }
           $params['is_sell']= $insertData['is_sell'];
            $result     = app('mysqlbxd_app')->insert('activity_handwriting_match_enroll',$params);
            return $result;
       }

        /**
         * 活动报名开始阶段   结束阶段
        */
       public static  function activity_config(){
           $redis    = app('redis');
           $config_handwriting_activity_vote = $redis->get('config_handwriting_activity_vote');
           if(!$config_handwriting_activity_vote){
               $s = "select skey,svalue from setting where skey in  ('handwriting_activity_vote','handwriting_activity_sing_up','handwriting_activity_review') ";
               //获取配置属性
               $result = app('mysqlbxd_mall_common')->select($s);
               $arr = [];
               foreach ($result as $k=>$value){
                   $arr[$value['skey']] = $value['svalue'];
               }
               unset($result);
               //计算到期时间
               $redis->set('config_handwriting_activity_vote',json_encode($arr));
               $config_handwriting_activity_vote = $redis->get('config_handwriting_activity_vote');
           }
           $config_handwriting_activity_vote = json_decode($config_handwriting_activity_vote,true);
           return $config_handwriting_activity_vote;
       }




    //---------------------------------------书画艺术大赛报名(2019-06-04) end--------------------------




}