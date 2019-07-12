<?php
namespace Lib\Wx;



use Framework\Helper\WxHelper;

class JsSdk
{
    /**
     * 网页签名
     */
    public function getJsSdkPageInitSign($url='')
    {
        if(!$url){
            $url=$this->getCurrentPageURL();
        }
        $params=[
            'timestamp'=>'',
            'noncestr'=>'',
            'signature'=>'',
            'appId'=>'',
        ];
        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'';
        if(strpos($user_agent, 'MicroMessenger')!==false){
            $params['timestamp'] = time();
            $params['noncestr'] = $this->getRandChar(32);
            $params['signature'] = $this->sign($params, $url);
            $params['appId'] = config('app.weChat.appid');
        }
        return  $params;
    }

    private function getCurrentPageURL()
    {
        $pageURL = 'https';
        /*
        $isHttps=empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 0 : 1;
        if ($isHttps)
        {
            $pageURL .= "s";
        }
        */
        $pageURL .= "://";
        /*
        if (!($_SERVER["SERVER_PORT"] == "80" || $_SERVER["SERVER_PORT"] == "443"))
        {
            $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        }
        else
        {
            $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        */
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        return $pageURL;
    }

    private function sign($params, $path)
    {

        $access_token = WxHelper::getAccessToken();

        //获取jsapi_ticket
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $access_token . '&type=jsapi';
        $res = $this->getCurl($url);
        if ($res['errcode'] == 0) {
            $jsapi_ticket = $res['ticket'];
        } else {
            return '';
        }
        $params['jsapi_ticket'] = $jsapi_ticket;
        $params['url'] = $path;  //'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $sign = $this->MakeSign($params);
        //var_dump($params);

        return $sign;
    }

    private function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $length; $i++) {
            // rand($min,$max)生成介于min和max两个数之间的一个随机整数
            $str .= $strPol[rand(0, $max)];
        }
        return $str;
    }

    private function MakeSign($data)
    {
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = $this->ToUrlParams($data);
        $string = sha1($string);

        return $string;
    }

    private function ToUrlParams($data)
    {
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    public function getCurl($url, $data = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        // 这一句是最主要的
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, TRUE);
    }
}
