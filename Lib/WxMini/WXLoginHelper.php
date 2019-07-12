<?php
namespace Lib\WxMini;

use Exception\ServiceException;
use Framework\Helper\SessionHelper;
use Lib\Common\SessionKeys;

/**
 * Created by PhpStorm.
 * User: raid
 * Date: 2017/1/13
 * Time: 17:16
 */

class WXLoginHelper {
    const SESSION_KEY_FLAG=1;
    const LOGIN_USER_INFO=2;
    const LOGIN_USER_INFO_DESCRYPTED=3;
    const UNIONID='unionId';
    const OPENID='openId';

    //默认配置
    protected $config = [
        'url' => "https://api.weixin.qq.com/sns/jscode2session", //微信获取session_key接口url
        'appid' => 'your appId', // APPId
        'secret' => 'your secret', // 秘钥
        'grant_type' => 'authorization_code', // grant_type，一般情况下固定的
        'wxminiType'=>''
    ];


    /**
     * 构造函数
     * WXLoginHelper constructor.
     */
    public function __construct($configKey='wx_mini_zwwh') {
        //可设置配置项 wxmini, 此配置项为数组。
        if ($wx = load_row_configs_trim_prefix('Thirdparty.UserLogin.'.$configKey)) {
            $this->config = array_merge($this->config, [
                'appid'=>$wx['app_key'],
                'secret'=>$wx['app_secret'],
            ]);
        }
        $this->config['wxminiType']=$configKey;
    }

    /***
     * @param $code
     * @param $rawData
     * @param $signature
     * @param $encryptedData
     * @param $iv
     * @return array|mixed
     *
     * {
    "openId": "OPENID",
    "nickName": "NICKNAME",
    "gender": GENDER,
    "city": "CITY",
    "province": "PROVINCE",
    "country": "COUNTRY",
    "avatarUrl": "AVATARURL",
    "unionId": "UNIONID",
    "watermark":
    {
    "appid":"APPID",
    "timestamp":TIMESTAMP
    }
    }

     * 参考： https://developers.weixin.qq.com/miniprogram/dev/api/open.html#wxgetuserinfoobject
     * @throws ServiceException
     */
    public function getUserInfo($rawData, $signature, $encryptedData, $iv) {
        $dataDe=$this->decryptData($encryptedData,$iv,'',$signature,$rawData);
        $userInfo=json_decode($rawData,true);
        SessionHelper::set([SessionKeys::USER_WX_MINI_SESSIONKEY,self::LOGIN_USER_INFO],$userInfo);
        SessionHelper::set([SessionKeys::USER_WX_MINI_SESSIONKEY,self::LOGIN_USER_INFO_DESCRYPTED],$dataDe);
        return $dataDe;
    }

    /**
     * @param $code
     * @return array
     * @throws ServiceException
     */
    public function checkLogin($code) {
        $wxSession=$this->getWxSessionByCode($code);
        SessionHelper::set([SessionKeys::USER_WX_MINI_SESSIONKEY,self::SESSION_KEY_FLAG],$wxSession);
        return $wxSession;
    }

    public function decryptData($encryptedData, $iv,$sessionKey='',$signature='',$rawData='') {
        if(!$sessionKey){
            $sessionKey=SessionHelper::get([
                SessionKeys::USER_WX_MINI_SESSIONKEY,
                self::SESSION_KEY_FLAG,
                'session_key'
            ]);
        }
        if(!$sessionKey){
            throw new ServiceException('缺少微信sessionKey');
        }
        /**
         * 5.server计算signature, 并与小程序传入的signature比较, 校验signature的合法性, 不匹配则返回signature不匹配的错误. 不匹配的场景可判断为恶意请求, 可以不返回.
         * 通过调用接口（如 wx.getUserInfo）获取敏感数据时，接口会同时返回 rawData、signature，其中 signature = sha1( rawData + session_key )
         *
         * 将 signature、rawData、以及用户登录态发送给开发者服务器，开发者在数据库中找到该用户对应的 session-key
         * ，使用相同的算法计算出签名 signature2 ，比对 signature 与 signature2 即可校验数据的可信度。
         */
        if($signature){
            $signature2 = sha1($rawData . $sessionKey);
            if ($signature2 !== $signature) throw new ServiceException('签名不匹配');
        }

        /**
         *
         * 6.使用第4步返回的session_key解密encryptData, 将解得的信息与rawData中信息进行比较, 需要完全匹配,
         * 解得的信息中也包括openid, 也需要与第4步返回的openid匹配. 解密失败或不匹配应该返回客户相应错误.
         * （使用官方提供的方法即可）
         */
        $pc = new WXBizDataCrypt($this->config['appid'], $sessionKey);
        $errCode = $pc->decryptData($encryptedData, $iv, $data );

        if ($errCode !== 0) {
//            wlog([
//                'DecryptData'=>[
//                    '$errCode'=>$errCode,
//                    '$encryptedData'=>$encryptedData,
//                    '$iv'=>$iv,
//                    '$data'=>$data,
//                    '$this->config[\'appid\']'=>$this->config['appid'],
//                    '$sessionKey'=>$sessionKey
//            ]],'wxminiLoginHelper-request-error');
            throw new ServiceException('解密信息错误');
        }


        /**
         * 7.生成第三方3rd_session，用于第三方服务器和小程序之间做登录态校验。为了保证安全性，3rd_session应该满足：
         * a.长度足够长。建议有2^128种组合，即长度为16B
         * b.避免使用srand（当前时间）然后rand()的方法，而是采用操作系统提供的真正随机数机制，比如Linux下面读取/dev/urandom设备
         * c.设置一定有效时间，对于过期的3rd_session视为不合法
         *
         * 以 $session3rd 为key，sessionKey+openId为value，写入memcached
         */
        $data = json_decode($data, true);
//        $session3rd = $this->randomFromDev(16);
//
//        $data['session3rd'] = $session3rd;
//        cache($session3rd, $data['openId'] . $sessionKey);

        return $data;
    }

    /**
     * 发起http请求
     * @param string $url 访问路径
     * @param array $params 参数，该数组多于1个，表示为POST
     * @param int $expire 请求超时时间
     * @param array $extend 请求伪造包头参数
     * @param string $hostIp HOST的地址
     * @return array    返回的为一个请求状态，一个内容
     */
    public function makeRequest($url, $params = array(), $expire = 0, $extend = array(), $hostIp = '')
    {
        if (empty($url)) {
            return array('code' => '100');
        }

        $_curl = curl_init();
        $_header = array(
            'Accept-Language: zh-CN',
            'Connection: Keep-Alive',
            'Cache-Control: no-cache'
        );
        // 方便直接访问要设置host的地址
        if (!empty($hostIp)) {
            $urlInfo = parse_url($url);
            if (empty($urlInfo['host'])) {
                $urlInfo['host'] = substr(DOMAIN, 7, -1);
                $url = "http://{$hostIp}{$url}";
            } else {
                $url = str_replace($urlInfo['host'], $hostIp, $url);
            }
            $_header[] = "Host: {$urlInfo['host']}";
        }

        // 只要第二个参数传了值之后，就是POST的
        if (!empty($params)) {
            curl_setopt($_curl, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($_curl, CURLOPT_POST, true);
        }

        if (substr($url, 0, 8) == 'https://') {
            curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($_curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($_curl, CURLOPT_URL, $url);
        curl_setopt($_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($_curl, CURLOPT_USERAGENT, 'API PHP CURL');
        curl_setopt($_curl, CURLOPT_HTTPHEADER, $_header);

        if ($expire > 0) {
            curl_setopt($_curl, CURLOPT_TIMEOUT, $expire); // 处理超时时间
            curl_setopt($_curl, CURLOPT_CONNECTTIMEOUT, $expire); // 建立连接超时时间
        }

        // 额外的配置
        if (!empty($extend)) {
            curl_setopt_array($_curl, $extend);
        }

        $result['result'] = curl_exec($_curl);
        $result['code'] = curl_getinfo($_curl, CURLINFO_HTTP_CODE);
        $result['info'] = curl_getinfo($_curl);
        if ($result['result'] === false) {
            $result['result'] = curl_error($_curl);
            $result['code'] = -curl_errno($_curl);
        }

        curl_close($_curl);
        return $result;
    }

    /**
     * 读取/dev/urandom获取随机数
     * @param $len
     * @return mixed|string
     */
    public function randomFromDev($len) {
        $fp = @fopen('/dev/urandom','rb');
        $result = '';
        if ($fp !== FALSE) {
            $result .= @fread($fp, $len);
            @fclose($fp);
        }
        else
        {
            trigger_error('Can not open /dev/urandom.');
        }
        // convert from binary to string
        $result = base64_encode($result);
        // remove none url chars
        $result = strtr($result, '+/', '-_');

        return substr($result, 0, $len);
    }


    /**
     * @param $code
     * @param string $configKey
     * @return array [
     * 'data'=>[
     *
     * ],
     * 'type'=>'']
     * @throws ServiceException
     */
    public function getWxSessionByCode($code)
    {
        $params = [
            'appid' => $this->config['appid'],
            'secret' => $this->config['secret'],
            'js_code' => $code,
            'grant_type' => $this->config['grant_type']
        ];

        $res = $this->makeRequest($this->config['url'], $params);
        wlog(['params'=>$params,'res'=>$res],'ss_ss',4);
        if ($res['code'] !== 200 || !isset($res['result']) || !isset($res['result'])) {
            wlog([
                'url'=>$this->config['url'],
                'param'=>$params,
                'response'=>$res
            ],'wxminiLoginHelper-request-error');
            throw new ServiceException('请求Token失败');
        }
        $reqData = json_decode($res['result'], true);
        if (!isset($reqData['session_key'])) {
            wlog($reqData,'wxminiLoginHelper-request-error');
            throw new ServiceException('请求Token失败');
        }
        /**
        //正常返回的JSON数据包
        {
        "openid": "OPENID",
        "session_key": "SESSIONKEY",
        }

        //满足UnionID返回条件时，返回的JSON数据包
        {
        "openid": "OPENID",
        "session_key": "SESSIONKEY",
        "unionid": "UNIONID"
        }
        //错误时返回JSON数据包(示例为Code无效)
        {
        "errcode": 40029,
        "errmsg": "invalid code"
        }
         */
        return $reqData;
    }

    /**
     * 获取小程序session
     * @return bool
     */
    public static function getWxSession($key=null)
    {
        if(!SessionHelper::exists(SessionKeys::USER_WX_MINI_SESSIONKEY)
            || empty(SessionHelper::get(SessionKeys::USER_WX_MINI_SESSIONKEY))){
            return false;
        }
        $sessionData=SessionHelper::get(SessionKeys::USER_WX_MINI_SESSIONKEY);
        if($key){
            if(isset($sessionData[self::LOGIN_USER_INFO_DESCRYPTED][$key])){
                return $sessionData[self::LOGIN_USER_INFO_DESCRYPTED][$key];
            }
        }
        return$sessionData;
    }
}