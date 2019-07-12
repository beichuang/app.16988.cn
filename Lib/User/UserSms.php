<?php
namespace Lib\User;

class UserSms extends UserBase
{

    /**
     * 发送短信
     */
    public function sendSms($params)
    {
        $resMall = $this->passRequest2User($params, 'user/send/sms');
        return $resMall;
    }

    /**
     * 发送短信（供控制台脚本调用）
     */
    public function cliSendSms($params)
    {
        //暂时先不发短信
        return true;
//        $resMall = $this->cliPassRequest2User($params, 'user/send/sms');
//        return $resMall;
    }

    /**
     * 发送文本短信（供控制台脚本调用）
     */
    public function cliSendSmsText($params)
    {
        $resMall = $this->cliPassRequest2User($params, 'user/send/sms/text');
        return $resMall;
    }
}
