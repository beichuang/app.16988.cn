<?php
/**
 * Created by PhpStorm.
 * User: jinjiabo
 * Date: 2018/5/3
 * Time: 15:30
 */

namespace Framework\Helper;


class RedisLock
{
    private $_redis = null;

    public function __construct()
    {
        $this->_redis = app('redis');
    }

    /**
     * 获取锁
     * @param  String $key 锁标识
     * @param  Int $expire 锁过期时间
     * @return Boolean
     */
    public function lock($key, $expire = 3)
    {
        $is_lock = $this->_redis->setnx($key, time() + $expire);

        // 不能获取锁
        if (!$is_lock) {
            // 判断锁是否过期
            $lock_time = $this->_redis->get($key);

            // 锁已过期，删除锁，重新获取
            if (time() > $lock_time) {
                $this->unlock($key);
                $is_lock = $this->_redis->setnx($key, time() + $expire);
            }
        }

        return $is_lock ? true : false;
    }

    /**
     * 释放锁
     * @param  String $key 锁标识
     * @return Boolean
     */
    public function unlock($key)
    {
        return $this->_redis->del($key);
    }
}