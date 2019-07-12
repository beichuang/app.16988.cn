<?php
/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/5/31
 * Time: 17:22
 */

namespace Framework\Helper;


class CookieHelper
{
    /**
     * 保存Cookie信息
     * @param string $key
     * @param string $value
     * @param int $expire
     */
    public static function setCookie($key, $value, $expire = 0, $cookiePath = '/')
    {
        if (empty($key)) {
            return;
        }
        if ($value == "") {
            setcookie($key, $value, time() - 3600, $cookiePath);
        } else {
            setcookie($key, $value, $expire, $cookiePath);
        }
    }

    /**
     * 查询 Cookie中key对应的value值
     * @param $key
     * @return string
     */
    public static function getCookie($key) {
        if (isset($_COOKIE[$key])) {
            return $_COOKIE[$key];
        }
        return "";
    }

}