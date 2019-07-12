<?php
/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/5/14
 * Time: 17:43
 */

namespace Controller\Wx;


use Framework\Helper\WxHelper;
use Lib\Base\BaseController;
use Lib\Wx\WechatMP;

class Index extends BaseController
{
    /**
     * 微信回调入口
     */
    public function index()
    {
        $options = array(
            'token'=>config('app.weChat.token'), //填写你设定的key
            'encodingaeskey'=>config('app.weChat.encodingaeskey'), //填写加密用的EncodingAESKey
            'appid'=>config('app.weChat.appid'), //填写高级调用功能的app id
            'appsecret'=>config('app.weChat.appSecret') //填写高级调用功能的密钥
        );
        $weObj = new WechatMP($options);
        $weObj->checkAuth('','',WxHelper::getAccessToken());
        $weObj->valid();
        $type = $weObj->getRev()->getRevType();
        switch($type) {
            case WechatMP::MSGTYPE_EVENT:
                $this->onMessageEvent($weObj);
                $this->replyMessage($weObj);
                break;
            case WechatMP::MSGTYPE_TEXT:
                if(!$this->replyMessage($weObj)){
                    //$weObj->transfer_customer_service()->reply();
                    //$this->replyBaiduEBridge($weObj);
                }
                break;
            default:
//                $weObj->transfer_customer_service()->reply();
                //$this->replyBaiduEBridge($weObj);
        }
    }

    /**
     * @param $weObj \Lib\Wx\WechatMP()
     */
    private function replyBaiduEBridge($weObj)
    {
        $weObj->text('感谢您的留言，我们稍后会回复您！如需及时回复，请点击在线客服！<a href="https://p.qiao.baidu.com/cps/chat?siteId=12769985&userId=26746183">【在线客服】</a>')->reply();
    }
    /**
     * 用户发消息过来
     * @param $weObj \Lib\Wx\WechatMP()
     */
    private function replyMessage($weObj)
    {
        $msgType = $weObj->getRevType();
        $openid=$weObj->getRevFrom();
        if($content=$weObj->getRevContent()){
            $replyList=$this->getWxMpReplyData($msgType,$content);
        }else if($eventArr=$weObj->getRevEvent()){
            $event=isset($eventArr['event'])?$eventArr['event']:'';
            $eventKey=isset($eventArr['key'])?$eventArr['key']:'';
            $content=$event.'&'.$eventKey;
            $replyList=$this->getWxMpReplyData($msgType,$content);
        }else{
            $replyList=[];
        }
        //是否被动回复消息了
        $isSendMsg=false;
        if(!$replyList){
            return $isSendMsg;
        }
        //从最后取一个，发被动消息
        $reply=array_pop($replyList);
        $this->replyMessageWxServer($weObj,$reply);
        if($replyList && count($replyList)>0){
            try{
                foreach ($replyList as $reply){
                    $this->replyMessageCustom($weObj,$reply);
                }
            }catch (\Exception $e){
                wlog([
                    'openid'=>$weObj->getRevFrom(),
                    'reply'=>$reply,
                    'getFile'=>$e->getFile(),
                    'getLine'=>$e->getLine(),
                    'getCode'=>$e->getCode(),
                    'getMessage'=>$e->getMessage(),
                    'getTraceAsString'=>$e->getTraceAsString(),
                ],'wxmp-reply-message-error');
            }
        }
        return $isSendMsg;
    }

    /**
     * 通过微信服务器被动回复发消息
     * @param $weObj \Lib\Wx\WechatMP()
     * @param $reply array
     */
    private function replyMessageWxServer($weObj,$reply)
    {
        $isSendMsg=false;
        if($reply['wmr_reply']){
            switch ($reply['wmr_replyType']){
                case WechatMP::MSGTYPE_TEXT:
                    $weObj->text($reply['wmr_reply'])->reply();
                    $isSendMsg=true;
                    break;
                case WechatMP::MSGTYPE_IMAGE:
                    $weObj->image($reply['wmr_reply'])->reply();
                    $isSendMsg=true;
                    break;
                case WechatMP::MSGTYPE_VOICE:
                    $weObj->voice($reply['wmr_reply'])->reply();
                    $isSendMsg=true;
                    break;
                case WechatMP::MSGTYPE_VIDEO:
                    $reData=json_decode($reply['wmr_reply'],true);
                    if(isset($reData['media_id']) && isset($reData['title']) && isset($reData['description'])){
                        $weObj->video($reData['media_id'],$reData['title'],$reData['description'])->reply();
                        $isSendMsg=true;
                    }
                    break;
                case WechatMP::MSGTYPE_MUSIC:
                    $reData=json_decode($reply['wmr_reply'],true);
                    if(isset($reData['title']) && isset($reData['desc']) && isset($reData['musicurl']) && isset($reData['hgmusicurl']) && isset($reData['thumbmediaid'])){
                        $weObj->music($reData['title'],$reData['desc'],$reData['musicurl'],$reData['hgmusicurl'],$reData['thumbmediaid'])->reply();
                        $isSendMsg=true;
                    }
                    break;
                case WechatMP::MSGTYPE_NEWS:
                    $reData=json_decode($reply['wmr_reply'],true);
                    if($reData && isset($reData[0]) && isset($reData[0]['Title']) && isset($reData[0]['Description']) && isset($reData[0]['PicUrl']) && isset($reData[0]['Url'])){
                        $weObj->news($reData)->reply();
                        $isSendMsg=true;
                    }
                    break;
            }
        }
        return $isSendMsg;
    }
    /**
     * 通过客服消息回复
     * @param $weObj \Lib\Wx\WechatMP()
     * @param $reply array
     */
    private function replyMessageCustom($weObj,$reply)
    {
        if($reply['wmr_reply']){
            $openid=$weObj->getRevFrom();
            switch ($reply['wmr_replyType']){
                case WechatMP::MSGTYPE_TEXT:
                    $weObj->sendCustomMessage([
                        'touser'=>$openid,
                        'msgtype'=>'text',
                        'text'=>[
                            'content'=>$reply['wmr_reply']
                        ],
                    ]);
                    break;
                case WechatMP::MSGTYPE_IMAGE:
                    $weObj->sendCustomMessage([
                        'touser'=>$openid,
                        'msgtype'=>'image',
                        'image'=>[
                            'media_id'=>$reply['wmr_reply']
                        ],
                    ]);
                    break;
                case WechatMP::MSGTYPE_VOICE:
                    $weObj->sendCustomMessage([
                        'touser'=>$openid,
                        'msgtype'=>'voice',
                        'voice'=>[
                            'media_id'=>$reply['wmr_reply']
                        ],
                    ]);
                    break;
                case WechatMP::MSGTYPE_VIDEO:
                    $reData=json_decode($reply['wmr_reply'],true);
                    if(isset($reData['media_id']) && isset($reData['title']) && isset($reData['description'])){
                        $weObj->sendCustomMessage([
                            'touser'=>$openid,
                            'msgtype'=>'video',
                            'video'=>[
                                'media_id'=>$reply['wmr_reply'],
                                "thumb_media_id"=>isset($reData['thumb_media_id'])?$reData['thumb_media_id']:'',
                                "title"=>$reData['title'],
                                "description"=>$reData['description']
                            ],
                        ]);
                    }
                    break;
                case WechatMP::MSGTYPE_MUSIC:
                    $reData=json_decode($reply['wmr_reply'],true);
                    if(isset($reData['title']) && isset($reData['desc']) && isset($reData['musicurl']) && isset($reData['hgmusicurl']) && isset($reData['thumbmediaid'])){
                        $weObj->sendCustomMessage([
                            'touser'=>$openid,
                            'msgtype'=>'music',
                            'music'=>[
                                "title"=>$reData['title'],
                                "description"=>$reData['desc'],
                                "musicurl"=>$reData['musicurl'],
                                "hqmusicurl"=>$reData['hgmusicurl'],
                                "thumb_media_id"=>$reData['thumbmediaid']
                            ],
                        ]);
                    }
                    break;
                case WechatMP::MSGTYPE_NEWS:
                    $reData=json_decode($reply['wmr_reply'],true);
                    if($reData && isset($reData[0]) && isset($reData[0]['Title']) && isset($reData[0]['Description']) && isset($reData[0]['PicUrl']) && isset($reData[0]['Url'])){
                        $articles=[];
                        foreach ($reData as $article){
                            $articles[]=
                                [
                                    "title"=>$article['Title'],
                                    "description"=>$article['Description'],
                                    "url"=>$article['Url'],
                                    "picurl"=>$article['PicUrl'],
                                ];
                        }
                        $weObj->sendCustomMessage([
                            'touser'=>$openid,
                            'msgtype'=>'news',
                            'news'=>[
                                'articles'=>$articles
                            ],
                        ]);
                    }
                    break;
                case WechatMP::MSGTYPE_MPNEWS:
                    $weObj->sendCustomMessage([
                        'touser'=>$openid,
                        'msgtype'=>'mpnews',
                        'mpnews'=>[
                            'media_id'=>$reply['wmr_reply']
                        ],
                    ]);
                    break;
            }
        }
    }
    private function getWxMpReplyData($msg_type,$msg)
    {
        $time=date('Y-m-d H:i:s');
        $reply=app('mysqlbxd_app')->select("select * from `wx_mp_reply` where wmr_msgType=:wmr_msgType and wmr_msg=:wmr_msg and wmr_endTime>'{$time}'  and wmr_startTime<'{$time}' order by wmr_updateTime limit 7 ",[
            'wmr_msgType'=>$msg_type,
            'wmr_msg'=>$msg,
        ]);
        if(!$reply){
            return [];
        }
        return $reply;
    }
    /**
     * 事件
     * @param $weObj \Lib\Wx\WechatMP()
     */
    private function onMessageEvent($weObj)
    {
        $eventArr=$weObj->getRevEvent();
        $event=isset($eventArr['event'])?$eventArr['event']:'';
        $eventKey=isset($eventArr['key'])?$eventArr['key']:'';
        switch ($event){
            case WechatMP::EVENT_SUBSCRIBE:
                $this->onEventSubscribe($weObj);
                break;
        }
        $this->saveEventLog($event,$eventKey,$weObj->getRevFrom());
    }
    /**
     * 保存事件日志
     */
    private function saveEventLog($event,$eventKey,$openid)
    {
        $data=[
            'el_time'=>date('Y-m-d H:i:s'),
            'el_event'=>$event,
            'el_eventKey'=>$eventKey,
            'el_openid'=>$openid,
        ];
        app('mysqlbxd_app')->insert('wx_mp_event_log',$data);
    }
    /**
     * 用户关注
     * @param $weObj \Lib\Wx\WechatMP()
     * {subscribe,openid,nickname,sex,city,province,country,language,headimgurl,subscribe_time,[unionid]}
     */
    private function onEventSubscribe($weObj)
    {
        $wxUserInfo=$weObj->getUserInfo($weObj->getRevFrom());
        wlog($wxUserInfo,'on-wx-subscribe');
        if ($wxUserInfo && $wxUserInfo['subscribe'] == 1) {
            //关注后新存
            $addData['uo_subscribe'] = $wxUserInfo['subscribe'];
            $addData['uo_openId'] = $wxUserInfo['openid'];
            $addData['uo_nickname'] = isset($wxUserInfo['nickname']) ? $wxUserInfo['nickname'] : '';
            $addData['uo_sex'] = isset($wxUserInfo['sex']) ? $wxUserInfo['sex'] : '';
            $addData['uo_headimgurl'] = isset($wxUserInfo['headimgurl']) ? $wxUserInfo['headimgurl'] : '';
            $addData['uo_subscribe_time'] = isset($wxUserInfo['subscribe_time']) ? date('Y-m-d H:i:s', $wxUserInfo['subscribe_time']) : '';
            //如果关注了，存入数据库
            $itemOpenidQuery = new \Lib\Mall\Goods();
            $itemOpenidQuery->itemOpenidQuery($addData);
        }
    }


    /**
     * 获取是否关注公众号
     */
    public function getIsSubscribe()
    {
        $openId=app()->request()->params('openId', '');
        $isSubscribe=0;
        $isBindMobile=0;
        if($openId){
            $return=WxHelper::getUserInfo(WxHelper::getAccessToken(),$openId);
            if(isset($return['subscribe'])){
                if ($return['subscribe'] == 1) {
                    $isSubscribe=1;
                }
                $isBindMobile=app('mysqlbxd_user')->fetchColumn("select count(*) from user_thirdparty_account where uta_thirdpartyId='WX_MP_10000' and uta_thirdpartyAccountId='{$openId}' and uta_status=0");

            }
        }
        return $this->responseJSON([
            'isSubscribe' => $isSubscribe,
            'isBindMobile' => $isBindMobile?1:0
        ]);
    }

    public function getOpenId()
    {
        $code = app()->request()->params('code', '');
        $redirectUrl = app()->request()->params('state', '');
        if (empty($code) || empty($redirectUrl)) {
            return '';
        }

        //防止调用多次
        if (stripos($redirectUrl, '/wx/index/getOpenId') !== false) {
            $redirectUrl = '';
        }

        $webAccessTokenData = WxHelper::getWebAccessToken($code);
        $openid = isset($webAccessTokenData["openid"]) ? $webAccessTokenData["openid"] : "";
        WxHelper::setCookieOpenId($openid);
        if ($redirectUrl) {
            app()->redirect($redirectUrl);
        }

        return $openid;
    }

    /**
     * 验证微信服务器签名
     * @param $signature
     * @param $timestamp
     * @param $nonce
     * @param $token
     * @return bool
     */
    private function checkSignature($signature, $timestamp, $nonce, $token)
    {
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        return $tmpStr == $signature;
    }
}