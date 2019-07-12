<?php
namespace ApiClient\CommonApi\Client\Sample;

class ApiClient
{

    protected $apiServerUrl = 'http://192.168.4.244/';

    protected $apiServerIp = '192.168.4.244';

    protected $apiAppId = '你的appid';

    protected $apiAppSecret = '你的appSecret';

    protected $timeOut = 3;

    public function __construct($appId, $appSecret, $serverUrl = '', $serverIp = '', $timeOut = 3)
    {
        $this->apiAppId = $appId;
        $this->apiAppSecret = $appSecret;
        if ($serverUrl) {
            $this->setServerUrl($serverUrl);
        }
        $this->apiServerIp = $serverIp;
        $this->timeOut = $timeOut;
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
     * @throws \Exception
     * @return mixed Ambigous mixed>|boolean
     */
    public function doRequest($action, $params = array(), $resJson = true, $timeOut = false)
    {
        $paramsBuild = $this->buildRequestParams($params);
        $url = $this->apiServerUrl . $action;
        $ip = $this->apiServerIp;
        $res_str = $this->curl($url, 'POST', $paramsBuild, $ip, $timeOut);
        if (! $res_str) {
            throw new \Exception("服务器响应空！");
        }
        wlog(
        [
            'url' => $url,
            'params' => $paramsBuild,
            'rawReturn' => $res_str
        ], 'CommonApi_access_' . str_replace(['/',"\\",":"], '_', $url), \Framework\Log::INFO);
        if ($resJson) {
            $res = json_decode($res_str, true);
            if (($json_err = json_last_error()) !== JSON_ERROR_NONE) {
                throw new \Exception("解析json错误！code:{$json_err}", $json_err);
            }
            if (! $res || ! is_array($res)) {
                throw new \Exception("解析服务器响应失败！");
            }
            if (isset($res['error_msg']) && $res['error_msg']) {
                throw new \Exception("服务器返回错误：" . $res['error_msg'], $res['error_code']);
            }
            return $res;
        } else {
            return $res_str;
        }
        return false;
    }

    public function setTimeOut($timeOut)
    {
        $this->timeOut = $timeOut;
        return $this;
    }

    public function setServerIp($serverIp)
    {
        $this->apiServerIp = $serverIp;
        return $this;
    }

    public function setServerUrl($serverUrl)
    {
        $serverUrl = rtrim($serverUrl, " /");
        $this->apiServerUrl = $serverUrl . "/";
        return $this;
    }

    public function setAppId($appId)
    {
        $this->apiAppId = $appId;
        return $this;
    }

    public function setAppSecret($appSecret)
    {
        $this->apiAppSecret = $appSecret;
        return $this;
    }

    private function buildRequestParams($extraParams)
    {
        $params = $extraParams;
        $params['appid'] = $this->apiAppId;
        $params['expires'] = strtotime("+10 minutes");
        $params['appsecret'] = $this->apiAppSecret;
        $params['signature'] = $this->makeSignature($params);
        unset($params['appsecret']);
        return $params;
    }

    private function makeSignature($data)
    {
        ksort($data);
        return md5(http_build_query($data));
    }
    // curl
    private function curl($url, $method = 'GET', $postdata = array(), $ip = '', $timeOut = false)
    {
        try {
            $urlInfo = parse_url($url);
            $host = $urlInfo['host'];
            if ($ip) {
                $url = preg_replace('/' . preg_quote($host) . '/', $ip, $url, 1);
            }
            if (! $timeOut) {
                $timeOut = $this->timeOut;
            }
            $ch = curl_init(); // 初始化curl
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);
            curl_setopt($ch, CURLOPT_URL, $url); // 抓取指定网页
            curl_setopt($ch, CURLOPT_HEADER, 0); // 设置header
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 要求结果
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)'); // USERAGENT
            curl_setopt($ch, CURLOPT_REFERER, $url); // referer
            curl_setopt($ch, CURLOPT_POST, $method == 'POST' ? 1 : 0); // post提交方式
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Host:' . $host
            ));
            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
            }
            $data = curl_exec($ch); // 运行curl
            $err = curl_error($ch);
            if ($err) {
                throw new \Exception("网络错误，{$err}");
            }
            if ($ch != null) {
                curl_close($ch);
            }
        } catch (\Exception $e) {
            if ($ch != null) {
                curl_close($ch);
            }
            $data = '';
            throw $e;
        }
        return $data;
    }
}

/**
 * 例子
$client = new ApiClient('oa','oa_secret');
$res=$client->doRequest('region/get');
var_dump($res);
*/