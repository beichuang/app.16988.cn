<?php
namespace ApiClient\CommonApi\Client\Sample;

use ApiClient\CommonApi\Unit\FileCache;

class CacheableApiClient extends ApiClient
{

    protected $fileCache = null;

    public function __construct($appId, $appSecret, $serverUrl = '', $serverIp = '', $timeOut = 3, $cache_dir = '/tmp/data',
        $lifetime = 31536000)
    {
        parent::__construct($appId, $appSecret, $serverUrl, $serverIp, $timeOut);
        $this->fileCache = new FileCache($cache_dir,$lifetime);
        return $this;
    }

    /**
     *
     * @param string $action
     *            请求的路径
     * @param array $params
     *            请求的参数
     * @param string $resJson
     *            是否对返回结果进行json格式化
     * @param string $timeOut
     *            curl超时，默认3s，设置成false则使用默认值
     * @param string $reload
     *            是否强制刷新缓存，默认false
     * @return Ambigous <boolean, mixed, \ApiClient\CommonApi\Client\Sample\mixed, string>
     */
    public function getData($action, $params = array(), $resJson = true, $timeOut = false, $reload = false)
    {
        if (! is_array($params)) {
            $params = array();
        }
        $cacheKey = $this->getCacheKey($action, $params);
        $data = $this->fileCache->get($cacheKey);
        if ($reload || ! $data) {
            $data = $this->doRequest($action, $params, $resJson, $timeOut);
            $this->fileCache->save($cacheKey, $data);
        }
        return $data;
    }

    private function getCacheKey($action, $params)
    {
        ksort($params);
        $str = serialize(array(
            $action,
            $params
        ));
        return md5($str);
    }
}

/**
 * 例子
$client = new CacheableApiClient('oa','oa_secret');
$res=$client->getData('region/get');
var_dump($res);
*/