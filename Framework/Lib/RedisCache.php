<?php

namespace Framework\Lib;

use Redis;
use Framework\Exception\RedisException;

class RedisCache extends Redis
{
    private $_key = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function connect($host, $port = 6379, $timeout = 0.0, $reserved = null, $retry_interval = 0)
    {
        if (!parent::connect($host, $port)) {
            throw new RedisException("cannot connect redis");
        }
        return $this;
    }
}
