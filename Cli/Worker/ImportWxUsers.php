<?php

/**
 * 将粉丝数据从微信拉到数据库
 */
namespace Cli\Worker;

use Framework\Helper\WxHelper;
use Lib\Mall\Voucher;

class ImportWxUsers
{

    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_user');
    }
    public function run()
    {
        $current=0;
        $nextOpenId='';
        $token=WxHelper::getAccessToken();
        do{
            $url="https://api.weixin.qq.com/cgi-bin/user/get?access_token={$token}&next_openid={$nextOpenId}";
            $res=file_get_contents($url);
            $data=json_decode($res,true);
            var_dump($url,$data['next_openid'],$data['count'],$data['total']);
            if($data['count']>0){
                $nextOpenId=$data['next_openid'];
                $openids=$data['data']['openid'];
                $this->saveOpenids($openids);
            }
        }while($data['count']>0);
    }

    private function saveOpenids($openids)
    {
        foreach ($openids as $openid){
            $data=$this->db->fetch('select * from `user_openId` where `uo_openId`=:uo_openId',[
                'uo_openId'=>$openid,
            ]);
            if($data && $data['uo_nickname'] && $data['uo_headimgurl']){
                continue;
            }else{
                $return=WxHelper::getUserInfo(WxHelper::getAccessToken(),$openid);
                if(isset($return['subscribe'])){
                    if ($return['subscribe'] == 1) {
                        //关注后新存
                        $addData['uo_subscribe'] = $return['subscribe'];
                        $addData['uo_openId'] = $openid;
                        $addData['uo_nickname'] = isset($return['nickname']) ? $return['nickname'] : '';
                        $addData['uo_sex'] = isset($return['sex']) ? $return['sex'] : '';
                        $addData['uo_headimgurl'] = isset($return['headimgurl']) ? $return['headimgurl'] : '';
                        $addData['uo_subscribe_time'] = isset($return['subscribe_time']) ? date('Y-m-d H:i:s', $return['subscribe_time']) : '';
                        if(isset($data['uo_id'])){
                            $this->db->update('user_openId',$addData,[
                                'uo_id'=>$data['uo_id']
                            ]);
                        }else{
                            $this->db->insert('user_openId',$addData);
                        }
                    }
                }
            }
        }
    }





}
