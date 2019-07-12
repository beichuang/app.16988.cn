<?php
/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/5/28
 * Time: 15:31
 */

namespace Framework\Helper;


class SessionHelper
{
    /**
     * 开启session
     */
    private static   function startSession($alwaysStartSession = true) {
        $expire = config('app.session.expire');
        // $sessionConfig = [
        // 'host' => config('database.redis.host'),
        // 'port' => config('database.redis.port'),
        // 'password' => '',
        // 'select' => 0,
        // 'expire' => $expire,
        // 'timeout' => 0,
        // 'persistent' => true,
        // 'session_name' => config('app.session.name_prefix')
        // ];
        $session_id_name = config('app.session.session_id_name', 'JPSESSID');
        session_name($session_id_name);
        // $handler = new RedisSessionHandler($sessionConfig);
        // ini_set("session.save_handler", "user");
        // session_set_save_handler(array(
        // $handler,
        // 'open'
        // ), array(
        // $handler,
        // 'close'
        // ), array(
        // $handler,
        // 'read'
        // ), array(
        // $handler,
        // 'write'
        // ), array(
        // $handler,
        // 'destroy'
        // ), array(
        // $handler,
        // 'gc'
        // ));

        $session_config=[
            'cookie_lifetime'=>$expire,
        ];
        $cookieSessionId = app()->getCookie($session_id_name);
        if (isset($cookieSessionId) && $cookieSessionId) {
            session_id($cookieSessionId);
            session_start($session_config);
        } else {
            if ($alwaysStartSession) {
                $isSuccess = @session_start($session_config);
                if (!$isSuccess) {
                    session_regenerate_id();
                    session_start($session_config);
                }
                app()->setCookie($session_id_name, session_id(), $expire + time());
            }
        }
    }

    /**
     * 重置sessionId
     */
    public static function sessionRegenerateId()
    {
        $session_id_name = config('app.session.session_id_name', 'JPSESSID');
        $expire = config('app.session.expire');
        session_regenerate_id();
        app()->setCookie($session_id_name, session_id(), $expire + time());
    }
    private static function getIsActive()
    {
        return session_status() == PHP_SESSION_ACTIVE;
    }

    private static function open($alwaysStartSession = true)
    {
        if (!self::getIsActive()) {
            self::startSession($alwaysStartSession);
        }
    }

    public static function set($key, $value)
    {
        self::open();
        if (is_array($key)) {
            if (count($key) == 1) {
                list($key1) = $key;
                $_SESSION[$key1] = $value;
            } elseif (count($key) == 2) {
                list($key1, $key2) = $key;
                $_SESSION[$key1][$key2] = $value;
            } elseif (count($key) == 3) {
                list($key1, $key2, $key3) = $key;
                $_SESSION[$key1][$key2][$key3] = $value;
            }
        } else {
            $_SESSION[$key] = $value;
        }
    }

    public static function get($key)
    {
        $value = '';
        self::open(false);
        if (is_array($key)) {
            if (count($key) == 1) {
                list($key1) = $key;
                $value = $_SESSION[$key1];
            } elseif (count($key) == 2) {
                list($key1, $key2) = $key;
                $value = $_SESSION[$key1][$key2];
            } elseif (count($key) == 3) {
                list($key1, $key2, $key3) = $key;
                $value = $_SESSION[$key1][$key2][$key3];
            }
        } else {
            $value = $_SESSION[$key];
        }

        return $value;
    }

    public static function exists($key)
    {
        self::open(false);
        return isset($_SESSION[$key]);
    }
}