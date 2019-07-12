<?php

/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/5/14
 * Time: 18:23
 */

namespace Framework\Helper;


class WxHelper
{
    public static function getOpenId($type = 1)
    {
        $openId = WxHelper::getCookieOpenId();
        if (!empty($openId)) {
            return $openId;
        } else {
            if (!app()->request->isAjax()) {
                //跳转到 获取openId地址
                $url = WxHelper::getAuthUrl(WxHelper::getCurrentUrl(), $type);
                app()->redirect($url);
            }
        }

        return '';
    }

    /**
     * 获取微信授权登录地址
     * @param string $redirectUrl
     * @param int $type
     * @return string
     */
    public static function getWxAuthUrl($redirectUrl = '', $type = 1)
    {
        $redirectUrl = $redirectUrl ?: WxHelper::getCurrentUrl();
        $url = WxHelper::getAuthUrl($redirectUrl, $type);
        return $url;

    }

    public static function getIsSubscribe($openId = '')
    {
        $isSubscribe = 0;
        if (!$openId) {
            $openId = self::getOpenId();
        }
        if ($openId) {
            $accessToken = self::getAccessToken();
            $data = self::getUserInfo($accessToken, $openId);
            $isSubscribe = empty($data['subscribe']) ? 0 : $data['subscribe'];
        }

        return $isSubscribe;
    }

    public static function getUserInfo($accessToken, $openId)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$accessToken}&openid={$openId}&lang=zh_CN";
        $content = file_get_contents($url);
        $res = json_decode($content, true);
        return $res;
    }

    /**
     * 获取access_token
     * @return mixed
     */
    public static function getAccessToken($key = 'wx_zw')
    {
        switch ($key) {
            case 'mp_yszz':
                $accessTokenKey = 'access_token_mp_yszz';
                $appId = config('Thirdparty.UserLogin.wx_mini_yszz.app_key');
                $appSecret = config('Thirdparty.UserLogin.wx_mini_yszz.app_secret');
                break;
            case 'mp_zwwh':
                $accessTokenKey = 'access_token_mp_zwwh';
                $appId = config('Thirdparty.UserLogin.wx_mini_zwwh.app_key');
                $appSecret = config('Thirdparty.UserLogin.wx_mini_zwwh.app_secret');
                break;
            default:
                $accessTokenKey = 'access_token_wx_zw';
                $appId = config('app.weChat.appid');
                $appSecret = config('app.weChat.appSecret');
                break;
        }

        $redis = app('redis');
        $accessToken = $redis->get($accessTokenKey);
        wlog("从缓存中获取：{$accessTokenKey}={$accessToken}",'wx-get-access-token');
        if (empty($accessToken)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}";
            $response = file_get_contents($url);
            wlog($response,'wx-get-access-token');
            $result = json_decode($response, true);

            if (isset($result['access_token'])) {
                $accessToken = $result['access_token'];
                $redis->setex($accessTokenKey, 6000, $accessToken);
            }
        }

        return $accessToken;
    }

    public static function getWebAccessToken($code)
    {
        $appId = config('app.weChat.appid');
        $appSecret = config('app.weChat.appSecret');
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appId}&secret=$appSecret&code={$code}&grant_type=authorization_code";
        $content = file_get_contents($url);
        $res = json_decode($content, true);
        if ($res && isset($res['access_token']) && $res['access_token']) {
            return $res;
        } else {
            throw new \Exception($content);
        }
    }

    public static function getAuthUrl($redirectUrl, $type = 1)
    {
        $appId = config('app.weChat.appid');
        $host = get_request_url_schema() . '://' . config('app.baseDomain');
        $getOpenIdUrl = $host . '/wx/index/getOpenId';

        return self::GetCodeUrl($appId, $getOpenIdUrl, $redirectUrl, $type);
    }

    /**
     * 判断访问来源是否是微信
     * @return bool
     */
    public static function isWeiXinPortal()
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        }
        return false;
    }

    /**
     * 获得当前的完整访问路径
     */
    public static function getCurrentUrl()
    {
        $request = app()->request;
        $host = get_request_url_schema() . '://' . config('app.baseDomain');
        return $host . $request->getPathInfo();
    }

    public static function getCookieOpenId()
    {
        $openId = CookieHelper::getCookie('openid');
        $checkOpenId = CookieHelper::getCookie('openid_check');
        if (!empty($openId) && !empty($checkOpenId)) {
            $appSecret = config('app.weChat.appSecret');
            if ($checkOpenId == md5($openId . $appSecret)) {
                return $openId;
            }
        } else {
            return '';
        }
    }

    public static function setCookieOpenId($openId)
    {
        $expire = time() + 60 * 60 * 24 * 7;
        //把openid追加到cookie中
        CookieHelper::setCookie('openid', $openId, $expire);
        if (!empty($openId)) {
            $appSecret = config('app.weChat.appSecret');
            // 加密 openid 防止用户伪造
            $checkOpenId = md5($openId . $appSecret);
            CookieHelper::setCookie('openid_check', $checkOpenId, $expire);
        }
    }

    private static function GetCodeUrl($appId, $redirectUrl, $state, $type)
    {
        $wxAuthUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize';
        $wxAuthUrl = $wxAuthUrl . "?appid={$appId}&redirect_uri={$redirectUrl}&response_type=code";

        if ($type == 1) {
            $wxAuthUrl = $wxAuthUrl . '&scope=snsapi_base&state=' . urlencode($state) . '#wechat_redirect';
        } else {
            $wxAuthUrl = $wxAuthUrl . '&scope=snsapi_userinfo&state=' . urlencode($state) . '#wechat_redirect';
        }

        return $wxAuthUrl;
    }
}