<?php

namespace Framework\Lib;

use Memcache;
use Framework\Exception;

class MemcacheCache
{

    private $memcache = null;
    private $expire = 300;

    public function __construct()
    {
        $this->memcache = new Memcache();
    }

    public function connect($host, $port = 11211)
    {
        $res = $this->memcache->connect($host, $port);
        if (!$res) {
            throw new MemcacheException('failed '.__FUNCTION__." {$host} {$port}");
        }
        return $this;
    }

    /**
     *
     * @param $key
     * @param $value
     * @param $expire 过期时间 秒
     * @throws MemcacheException 添加key失败
     */
    public function add($key, $value, $expire = -1)
    {
        if ($expire < 0) {
            $expire = $this->expire;
        }
        $res = $this->memcache->add($key, $value, 0, $expire);
        if (!$res) {
            throw new MemcacheException('failed '.__FUNCTION__." key({$key}) value:".var_export($value, true)." expire: {$expire}");
        }
    }

    public function set($key, $value, $expire = -1)
    {
        if ($expire < 0) {
            $expire = $this->expire;
        }
        $res = $this->memcache->set($key, $value, 0, $expire);
        if (!$res) {
            throw new MemcacheException('failed '.__FUNCTION__." key({$key}) value:".var_export($value, true)." expire: {$expire}");
        }
    }

    public function replace($key, $value, $expire = -1)
    {
        if ($expire < 0) {
            $expire = $this->expire;
        }
        $res = $this->memcache->replace($key, $value, 0, $expire);
        if (!$res) {
            throw new MemcacheException('failed '.__FUNCTION__." key({$key}) value:".var_export($value, true)." expire: {$expire}");
        }
        return $this;
    }

    public function get($key)
    {
        return $this->memcache->get($key);
    }

    public function delete($key)
    {
        $res = $this->memcache->delete($key);
        if (!$res) {
            throw new MemcacheException('failed '.__FUNCTION__." key({$key})");
        }
        return $this;
    }

    public function flush()
    {
        $res = $this->memcache->flush();
        if (!$res) {
            throw new MemcacheException('failed '.__FUNCTION__);
        }
        return $this;
    }

    public function close()
    {
        $res = $this->memcache->close();
        if (!$res) {
            throw new MemcacheException('failed '.__FUNCTION__);
        }
        return $this;
    }
}
