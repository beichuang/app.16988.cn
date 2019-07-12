<?php

use Noodlehaus\Config;
use \Framework\Core;

if (!function_exists('config')) {

    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function config($key = 'app', $default = null) {
        $result = array();
        $hasKey = strpos($key, '.') > -1;
        if ($hasKey) {
            $file = strstr($key, '.', true);
            $realKey = strstr($key, '.');
            $realKey = substr($realKey, 1);
            $envValue = env($realKey);
            if (!is_null($envValue)) {
                return $envValue;
            }
        } else {
            $file = $key;
        }

        $configPath = app()->baseDir . DIRECTORY_SEPARATOR . 'Config'
                . DIRECTORY_SEPARATOR . $file . '.php';
        $configs = Config::load($configPath);
        if ($hasKey) {
            $value = $configs->get($realKey, $default);
            if (is_array($value)) {
                $result[$realKey] = $value;
            } else {
                return $value;
            }
        } else {
            $result = $configs->all();
        }
        $flattenedConf = \Framework\Helper\Arr::flatten($result);
        $env = env();
        foreach ($flattenedConf as $key => $value) {
            $flattenedConf[$key] = isset($env[$key]) ? $env[$key] : $value;
        }
        return $flattenedConf;
    }

}
if (!function_exists('conf')) {

    /**
     * 仅获取config下的内容
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function conf($key = 'app', $default = null) {
        $result = array();
        $hasKey = strpos($key, '.') > -1;
        if ($hasKey) {
            $file = strstr($key, '.', true);
            $realKey = strstr($key, '.');
            $realKey = substr($realKey, 1);
        } else {
            $file = $key;
        }

        $configPath = app()->baseDir . DIRECTORY_SEPARATOR . 'Config'
                . DIRECTORY_SEPARATOR . $file . '.php';
        $configs = Config::load($configPath);
        if ($hasKey) {
            $value = $configs->get($realKey, $default);
            return $value;
        } else {
            return $configs->all();
        }
    }

}

if (!function_exists('app')) {

    /**
     * Get the available container instance.
     * @param  string  $make
     */
    function app($make = null) {
        $app = Core::getSelf();
        if (is_null($make)) {
            return $app;
        }

        return $app->container[$make];
    }

}

if (!function_exists('template')) {

    /**
     * Get the available container instance.
     *
     * @param  string  $make
     * @return mixed
     */
    function template($file) {
        return app()->view->complie($file);
    }

}

if (!function_exists('env')) {

    /**
     * Get env setting
     *
     * @param  string  $key
     * @param  string  $default default value
     * @return 返回key对应的值，如果没有返回默认值，如果默认值是null则返回所有
     */
    function env($key = null, $default = null) {
        $envFile = app()->baseDir . DIRECTORY_SEPARATOR . '.env';
        $env = array();
        $res = null;
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
        }
        if ($key === null) {
            $res = $env;
        } elseif (isset($env[$key])) {
            $res = $env[$key];
        } else {
            $res = $default;
        }
        return $res;
    }

}

if (!function_exists('str_random')) {

    /**
     * Generate "random" alpha-numeric string.
     *
     * @param  int  $length
     * @param  int  $t upper-number 类似linux文件权限，每位用二进制表示 11表示有大写字符、有数字。
     *        10表示有大写字符没有数字
     * @return string
     */
    function str_random($length = 16, $t = 3) {
        return Str::random($length);
    }

}

if (!function_exists('wlog')) {

    /**
     * 写日志
     * @param  mixed  $message
     * @param  string     $file
     * @param  string  $end
     * @return string
     */
    function wlog($message, $file = '', $level = \Framework\Log::NOTICE) {
        $now = app('carbon')->now();
        $date = $now->format('Y-m');
        if (empty($file)) {
            $file = "application";
        }
        $dateDir = $now->format('Ymd');
        $logPath = app()->baseDir . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR . 'Logs' . DIRECTORY_SEPARATOR;
        $dateFullDir = $logPath . $dateDir;
        $datdDirExists = is_dir($dateFullDir);
        if (!$datdDirExists) {
            if (!mkdir($dateFullDir)) {
                throw new \Exception("创建目录出现错误！{$dateFullDir}");
            }
            if (!chmod($dateFullDir, 0777)) {
                throw new \Exception("更改目录的权限时出现错误！{$dateFullDir}");
            }
        }
        $filePath = $dateFullDir . DIRECTORY_SEPARATOR . $file . '.log';
        $filePathExists = file_exists($filePath);
        $oldRes = app('logWriter')->getResource();
        $logWriter = app('logWriter')->setResource(fopen($filePath, 'a'));
        if (!$filePathExists) {
            chmod($filePath, 0777);
        }
        app('log')->setWriter($logWriter)->log($level, $message);
//        app('logWriter')->setResource(fopen($filePath, 'a'));
        app('logWriter')->setResource($oldRes);
    }

}

if (!function_exists('get_request_url_schema_x_forwarded_proto')) {

    function get_request_url_schema_x_forwarded_proto() {
        if (PHP_SAPI == 'cli') {
            return config('app.request_url_schema_x_forwarded_proto_default', 'https');
        }
        $protocol = app()->request()->headers('X-Forwarded-Proto', '');
        if ($protocol && in_array(strtolower($protocol), ['http', 'https'])) {
            return strtolower($protocol);
        } else {
            return config('app.request_url_schema_x_forwarded_proto_default', 'https');
        }
    }

}

function create_folder($path) {
    if (!file_exists($path)) {
        create_folder(dirname($path));
        mkdir($path, 0777);
        @chmod($path, 0777);
    }
}

/**
 * 敏感词过滤
 * @param type $content 要检测的内容
 * @param type $replace 敏感词替换
 * @return array
 */
function filter_words($content, $replace = '') {
    $filter_words_path = app()->baseDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'filter_words.txt';
    if (is_file($filter_words_path)) {
        $filter_words = file_get_contents($filter_words_path);
    }
    $filter_words_arr = explode('|', $filter_words);
    if (empty($replace)) {
        $matches = [];
        for ($i = 0; $i < count($filter_words_arr); $i++) {
            if (preg_match("/" . trim($filter_words_arr[$i]) . "/i", $content, $matches)) {//应用正则表达式，判断传递的留言信息中是否含有敏感词
                return $matches[0];
            }
        }
        return $matches;
    } else {
        $bad_words_arr = array_combine($filter_words_arr, array_fill(0, count($filter_words_arr), $replace));
        return strtr($content, $bad_words_arr);
    }
}

/**
 * 去除所有HTML标签
 * @param type $string
 * @param type $sublen
 * @return type
 */
function cutstr_html($string, $sublen = null) {
    $string = strip_tags($string);
    $string = trim($string);
    $string = preg_replace('/\n/is', '', $string);
    $string = preg_replace('/ |　/is', '', $string);
    $string = preg_replace('/&nbsp;/is', '', $string);

    if (isset($sublen)) {
        preg_match_all("/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/", $string, $t_string);
        if (count($t_string[0]) - 0 > $sublen) {
            $string = join('', array_slice($t_string[0], 0, $sublen)) . "…";
        } else {
            $string = join('', array_slice($t_string[0], 0, $sublen));
        }
    }
    return $string;
}

/**
 * 输出jsonp格式的http响应
 *
 * @param unknown $data
 * @param string $status
 * @param number $encodingOptions
 */
function jsonp_response($data, $status = null, $encodingOptions = 0) {
    $app = app();
    $jsonp = jsonp_format($data, $encodingOptions);
    $app->response()->setBody($jsonp);
    $app->response()->headers->set('Content-Type', 'application/javascript;charset=utf-8');
    $app->response()->headers->set('Access-Control-Allow-Origin', '*');
    if (isset($status)) {
        $this->setStatus($status);
    }
}

/**
 * 从request中取出jsonp的回调函数名称
 *
 * @return Ambigous <multitype:, \Framework\Http\mixed, NULL, mixed, unknown>
 */
function jsonp_callback_name() {
    $API_JSONP_CALLBACK = "api.http_param_name.jsonp_callback";
    $app = app();
    $callback_param_name = config($API_JSONP_CALLBACK);
    $jsonp_callback_func_name = $app->request()->params($callback_param_name);
    return $jsonp_callback_func_name;
}

/**
 * 将一个值转成JSONP字符串
 *
 * @param unknown $value
 * @param string $options
 * @throws \RuntimeException
 * @return string
 */
function jsonp_format($value, $options = null) {
    $callback_func_name = jsonp_callback_name();
    $jsonObj = json_encode($value, $options);
    if ($jsonObj === false) {
        throw new \RuntimeException(json_last_error_msg(), json_last_error());
    }
    if (!$jsonObj) {
        $jsonObj = '{}';
    }
    return $callback_func_name . "(" . $jsonObj . ");";
}

/**
 * 判断一个请求是否是JSONP请求
 *
 * @param string $callback_func_name
 * @return boolean
 */
function is_jsonp_request() {
    $callback_func_name = jsonp_callback_name();
    if (is_string($callback_func_name) && !empty(trim($callback_func_name))) {
        return true;
    }
    return false;
}

function is_cli() {
    return PHP_SAPI == 'cli' ? 1 : 0;
}

function cli_print($str, $showtime = false) {
    if ($showtime) {
        $str = udate("Y-m-d H:i:s.u") . "  " . $str;
    }
    // if(strtoupper(substr(PHP_OS,0,3)) === 'WIN'){
    // $str=iconv('iso8859-1', 'gbk', $str);
    // }
    print($str . PHP_EOL);
}

function udate($format = 'u', $utimestamp = null) {
    if (is_null($utimestamp))
        $utimestamp = microtime(true);

    $timestamp = floor($utimestamp);
    $milliseconds = round(($utimestamp - $timestamp) * 1000000);

    return date(preg_replace('`(?<!\\\\)u`', $milliseconds, $format), $timestamp);
}

/**
 * 获取客户端IP地址
 *
 * @param integer $type
 *            返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv
 *            是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0, $adv = false) {
    $type = $type ? 1 : 0;
    static $ip = NULL;
    if ($ip !== NULL)
        return $ip[$type];
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos)
                unset($arr[$pos]);
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array(
        $ip,
        $long
    ) : array(
        '0.0.0.0',
        0
    );
    return $ip[$type];
}

/**
 * 从请求中获取appid
 *
 * @return string
 */
function get_appid_from_request() {
    $APPID_HTTP_PARAM_NAME = "api.http_param_name.appid";
    $appid_md5_str = app()->request()->params(config($APPID_HTTP_PARAM_NAME));
    $appid = config('api.reg_apps.' . $appid_md5_str . '.app_id');
    return $appid;
}

/**
 * 把扁平化的配置数组，转成多维数组
 *
 * @param string $configs
 * @return array
 */
function load_row_configs($configs) {
    $res = array();
    if ($configs && is_array($configs) && count($configs) > 0) {
        $subConfigsTmp = array();
        foreach ($configs as $k => $v) {
            $pos = strpos($k, '.');
            if ($pos === false) {
                $res[$k] = $v;
            } else {
                $sub_k = substr($k, $pos + 1);
                $k = substr($k, 0, $pos);
                $subConfigsTmp[$k][$sub_k] = $v;
            }
        }
        if (count($subConfigsTmp) > 0) {
            foreach ($subConfigsTmp as $k => $v) {
                $res[$k] = load_row_configs($v);
            }
        }
    }
    return $res;
}

/**
 * 把扁平化的配置数组，转成多维数组,并去掉前缀
 *
 * @param string $prefix
 * @param string $configFile
 * @return array
 */
function load_row_configs_trim_prefix($prefix) {
    if (($pos = strpos($prefix, '.')) !== false) {
        $configFile = substr($prefix, 0, $pos);
    } else {
        $configFile = $prefix;
    }
    $tmpConfig = config($prefix);
    if (!$tmpConfig || !is_array($tmpConfig)) {
        return $tmpConfig;
    }
    $tmpConfig2 = array();
    foreach ($tmpConfig as $k => $v) {
        $k = str_replace($prefix, '', $configFile . '.' . $k);
        if (strpos($k, '.') === 0) {
            $k = substr($k, 1);
        }
        $tmpConfig2[$k] = $v;
    }
    $configs = load_row_configs($tmpConfig2);
    return $configs;
}

/**
 * 将数据从数据库导出到csv文件
 *
 * @param string $file
 * @param string $sql
 * @param array $sqlBindData
 * @param array $header
 */
function export_db_data_to_file($file, $sql, $sqlBindData = array(), $header = array()) {
    $mysql = app('mysqlbxd_app');
    $mysql = new MySql();
    $data = $mysql->select($sql, $sqlBindData);
    if ($data && count($data) > 0) {
        $fp = fopen($file, 'w');
        if (!$header) {
            foreach ($data[0] as $k => $v) {
                $header[] = $k;
            }
        }
        fputcsv($fp, $header);
        foreach ($data as $row) {
            $csvRow = array();
            foreach ($header as $field) {
                $csvRow[] = $row[$field];
            }
            fputcsv($fp, $csvRow);
        }
        fclose($fp);
    }
}

/**
 * 退出任务
 *
 * @param int $restartStart
 * @param int $restartEnd
 * @param int $code
 */
function exitTask($restartStart, $restartEnd, $code = 0) {
    $time = date('H:i:s');
    if ($time >= $restartStart && $time <= $restartEnd) {
        exit($code);
    }
}

/**
 * 检查签名
 *
 * @param string $appId
 * @param string $appSecret
 * @return boolean
 */
function signatureCheck($appId, $appSecret) {
    $params = array_merge($_GET, $_POST);
    if (!isset($params['appid']) || !$params['appid'] || $params['appid'] != $appId) {
        return false;
    }
    if (!isset($params['signature']) || !$params['signature']) {
        return false;
    }
    $signature = $params['signature'];
    $data = array();
    foreach ($params as $k => $v) {
        if ($k != 'signature') {
            $data[$k] = $v;
        }
    }
    $data['appsecret'] = $appSecret;
    ksort($data);
    return md5(http_build_query($data)) == $signature;
}

/**
 * 对一组参数生成签名
 *
 * @param string $appid
 * @param string $appSecret
 * @param array $params
 *            参数数组
 * @param number $expires
 *            分钟
 * @return string
 */
function signatureMake($appid, $appSecret, $params = array(), $expires = 10) {
    $params['appid'] = $appid;
    if (!$expires) {
        $expires = 10;
    }
    $params['expires'] = strtotime("+{$expires} minutes");
    $params['appsecret'] = $appSecret;
    ksort($params);
    $params['signature'] = md5(http_build_query($params));
    unset($params['appsecret']);
    return $params;
}

/**
 * 获取
 *
 * @param \Exception $e
 * @return multitype:NULL |\Exception
 */
function get_exception_simple_info($e) {
    if ($e instanceof \Exception) {
        return array(
            'errorMessage' => $e->getMessage(),
            'errorFile' => $e->getFile(),
            'errorLine' => $e->getLine(),
            'errorCode' => $e->getCode(),
            'errorTracing' => $e->getTraceAsString()
        );
    } else {
        return $e;
    }
}

function wlog_exception($e, $file = '', $level = \Framework\Log::ERROR) {
    if (!$file) {
        $file = str_replace("/", "_", $e->getFile());
    }
    $errors = get_exception_simple_info($e);
    wlog($errors, $file, $level);
}

function get_static_resource_control_method($ctlMethod) {
    if (!strpos($ctlMethod, '.')) {
        return $ctlMethod;
    }
    $method = $prefix = $suffix = '';
    if ($ctlMethod) {
        $arr = explode('.', $ctlMethod);
        if (!empty($arr)) {
            $prefix = array_shift($arr);
            if (!empty($arr)) {
                foreach ($arr as $s) {
                    if ($s) {
                        $suffix .= ucfirst(strtolower($s));
                    }
                }
            }
        }
        $method = $prefix . $suffix;
    }
    return $method;
}

/**
 * 示例:
 * $userClient=get_api_client('User');
 * $mallClient=get_api_client('Mall');
 * $commonApiClient=get_api_client('CommonData','ApiClient');
 * $commonCacheableApiClient=get_api_client('CommonData','CacheableApiClient');
 * $commonSmsApiClient=get_api_client('CommonData','SmsClient');
 *
 * @param string $ConfigName
 * @param string $type
 * @throws \Exception
 * @return Ambigous <mixed, \Illuminate\Foundation\Application, \Framework\Core>|unknown
 */
function get_api_client($ConfigName, $type = 'RestClient') {
    $key = 'api-client-' . $ConfigName.$type;
    $client = app()->container->get($key);
    if ($client) {
        return $client;
    }
    $config = load_row_configs_trim_prefix('api.' . $ConfigName);
    if (is_array($config) && isset($config['serverUrl']) && isset($config['appId']) && isset($config['appSecret'])) {
        $appId = $config['appId'];
        $appSecret = $config['appSecret'];
        $serverUrl = $config['serverUrl'];
        $serverIp = isset($config['serverIp']) ? $config['serverIp'] : '';
        switch ($type) {
            case 'RestClient':
                $client = new \Lib\Common\RestClientAdapter();//new \ApiClient\RestClient($appId, $appSecret, $serverUrl, $serverIp);
                break;
            case 'ApiClient':
            case 'CacheableApiClient':
            case 'SmsClient':
                $class = "\\ApiClient\\CommonApi\\Client\\" . $type;
                $timeOut = $config['defaultRequestTimeOut'];
                $cachePath = $config['cachePath'];
                $cacheLifetime = $config['cacheLifetime'];
                $client = new $class($appId, $appSecret, $serverUrl, $serverIp, $timeOut, $cachePath, $cacheLifetime);
                break;
            default:
                throw new \Exception("获取Api客户端失败");
                break;
        }
        app()->container->singleton($key, function ($c) use($client) {
            return $client;
        });
        return $client;
    } else {
        throw new \Exception("获取Api客户端失败");
    }
}

/**
 * 将请求转发到指定URI
 * @param type $params
 * @param type $uri
 */
function api_request($params, $uri, $safe_level = 1) {
    $uri_arr = explode('/', $uri);
    $url = get_request_url_schema() . '://' . app()->request()->getHost();
    if ((app()->request()->getScheme() === 'https' && app()->request()->getPort() !== 443) || (app()->request()->getScheme() === 'http' && app()->request()->getPort() !== 80)) {
        $url .= sprintf(':%s', app()->request()->getPort());
    }

    $api = get_api_client(ucfirst($uri_arr[0]));
    $api->chooseRequest($uri, $safe_level)->setParams($params);
    $api->setHeader('X-Forwarded-Proto', get_request_url_schema());
    $api->setHeader('X-Forwarded-URL', $url);
    $res = $api->execRequest();
    if ($res->code != 200) {
        throw new \Exception($res->data, $res->code);
    }
    return $res->data;
}

function null_value_to_empty_string_filter($var) {
    if (is_array($var) && count($var) > 0) {
        foreach ($var as $k => $v) {
            if ($v === null) {
                $v = '';
            } else if (is_array($v) && count($v) > 0) {
                $v = null_value_to_empty_string_filter($v);
            }
            $var[$k] = $v;
        }
    }
    return $var;
}

function ftp_get_visit_url($configKey, $fileFtpPath) {
    if (!$fileFtpPath) {
        return '';
    }
    $urlPath = config("app.ftp.path.{$configKey}.imgDomainPath");
    $fileFtpPath = ltrim($fileFtpPath, '/');
    return get_request_url_schema() . ':' . $urlPath . '/' . $fileFtpPath;
}

function get_request_url_schema() {
    return get_request_url_schema_x_forwarded_proto();
}



/**
 * 格式化成适合显示的时间
 *
 * @param int $time
 *            UNIX时间戳
 * @return string
 */
function date_format_to_display($time) {
    $nowtime = time();
    $difference = $nowtime - $time;
    $msg = '';
    if ($difference <= 60) {
        $msg = '刚刚';
    } else if ($difference > 60 && $difference <= 3600) {
        $msg = floor($difference / 60) . '分钟前';
    } else if ($difference > 3600 && $difference <= 86400) {
        $msg = floor($difference / 3600) . '小时前';
    } else if ($difference > 86400 && $difference <= 604800) {   //1-7天
        $msg = floor($difference / 86400) . '天前';
    } else if ($difference > 604800 && $difference <= 2592000) {
        $msg = floor($difference / 604800) . '周前';
    } else if ($difference > 2592000) {
        $msg = '1个月前';
    }
    /* if ($difference <= 60) {
      $msg = '刚刚';
      } else if ($difference > 60 && $difference <= 3600) {
      $msg = floor($difference / 60) . '分钟前';
      } else if ($difference > 3600 && $difference <= 86400) {
      $msg = floor($difference / 3600) . '小时前';
      } else if ($difference > 86400 && $difference <= 2592000) {
      $msg = floor($difference / 86400) . '天前';
      } else if ($difference > 2592000 && $difference <= 7776000) {
      $msg = floor($difference / 2592000) . '个月前';
      } else if ($difference > 7776000) {
      $msg = '很久以前';
      } */
    return $msg;
}

function timediff($begin_time, $end_time) {
    if ($begin_time < $end_time) {
        $starttime = $begin_time;
        $endtime = $end_time;
    } else {
        $starttime = $end_time;
        $endtime = $begin_time;
    }
    //天
    $timediff = $endtime - $starttime;
    $days = intval($timediff / 86400);
    //时
    $remain = $timediff % 86400;
    $hours = intval($remain / 3600);
    //分
    $remain = $remain % 3600;
    $mins = intval($remain / 60);
    //秒
    $secs = $remain % 60;
    $res = array("day" => $days, "hour" => $hours, "min" => $mins, "sec" => $secs);
    return $res;
}

/**
 * 查询图片类型
 *
 * @param unknown $typeInt
 * @return string
 */
function get_image_type($targetFile) {
    $type = 0;
    try {
        $imageInfo = @getimagesize($targetFile);
        $type = $imageInfo[2];
    } catch (\Exception $e) {
        return '';
    }
    $types = [
        1 => 'GIF',
        2 => 'JPG',
        3 => 'PNG',
        4 => 'SWF',
        5 => 'PSD',
        6 => 'BMP',
        7 => 'TIFF(intel byte order)',
        8 => 'TIFF(motorola byte order)',
        9 => 'JPC',
        10 => 'JP2',
        11 => 'JPX',
        12 => 'JB2',
        13 => 'SWC',
        14 => 'IFF',
        15 => 'WBMP',
        16 => 'XBM'
    ];
    return isset($types[$type]) ? $types[$type] : '';
}

/**
 * 判断是否手机访问
 */
function is_mobile_request() {
    static $is_mobile;
    "Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1";
    if (isset($is_mobile))
        return $is_mobile;
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        $is_mobile = false;
    } elseif (preg_match(
        "/(MIDP)|(WAP)|(UP.Browser)|(Smartphone)|(Obigo)|(Mobile)|(AU.Browser)|(wxd.Mms)|(WxdB.Browser)|(CLDC)|(UP.Link)|(KM.Browser)|(UCWEB)|(SEMC-Browser)|(Mini)|(Symbian)|(Palm)|(Nokia)|(Panasonic)|(MOT-)|(SonyEricsson)|(NEC-)|(Alcatel)|(Ericsson)|(BENQ)|(BenQ)|(Amoisonic)|(Amoi-)|(Capitel)|(PHILIPS)|(SAMSUNG)|(Lenovo)|(Mitsu)|(Motorola)|(SHARP)|(WAPPER)|(LG-)|(LG\/)|(EG900)|(CECT)|(Compal)|(kejian)|(Bird)|(BIRD)|(G900\/V1\.0)|(Arima)|(CTL)|(TDG)|(Daxian)|(DAXIAN)|(DBTEL)|(Eastcom)|(EASTCOM)|(PANTECH)|(Dopod)|(Haier)|(HAIER)|(KONKA)|(KEJIAN)|(LENOVO)|(Soutec)|(SOUTEC)|(SAGEM)|(SEC-)|(SED-)|(EMOL-)|(INNO55)|(ZTE)|(iPhone)|(Android)|(Windows CE)|(Wget)|(Java)|(Opera)/", $_SERVER['HTTP_USER_AGENT'])) {
        $is_mobile = true;
    } else {
        $is_mobile = false;
    }
    return $is_mobile;
}

/* -- 调试输出函数 -- */

function d($val = '', $name = '', $type = false) {
    static $i = 1;
    ($i < 2) && header('Content-Type:text/html;charset=utf8');
    $dbArr = debug_backtrace();
    $dbStr = '<span style="padding-left:15px;/*color:#aaa;*/">{Path:';
    isset($dbArr[0]) && ($dbStr .= $dbArr[0]['file'] . ' [line:' . $dbArr[0]['line'] . ']');
    $dbStr .= '}</span>';
    $str = '<hr /><pre style="-moz-border-radius:8px;width:99%;overflow:auto;margin:0;padding:1px 6px 6px;font-size:15px;border:1px solid #BBB;background-color:#DDD;color:green;">';
    $nameTmp = str_replace('die', '', $name);
    $str .= ($nameTmp === '') ? '<h3 style="background-color:#AAA;padding:2px 0 2px 3px;margin:0 0 15px;border-bottom:1px dashed #666;font-weight:normal;">{' .
        $i ++ . '}' . $dbStr . '</h3>' : ('<h3 style="background-color:#AAA;padding:2px 0;margin:0 0 15px;border-bottom:1px dashed #666;"><span style="font-weight:normal;padding-left:3px;margin:0 10px 0 0;">{' .
        $i ++ . '}</span>' . $nameTmp . '' . $dbStr . '</h3>');
    echo $str;
    ($type || empty($val)) ? var_dump($val) : print_r($val);
    echo '</pre><hr />';
    (strpos($name, 'die') !== false) && die();
}

/**
 * 对象转换为数组
 * @param unknown $array
 */
function object_array($array) {
    if (is_object($array)) {
        $array = (array) $array;
    } if (is_array($array)) {
        foreach ($array as $key => $value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

/**
 * 生成随机验证
 */
function getRandChar($length)
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


//------------------- 根据ip  获取所在城市 start-----------------
/**
 * 获取IP地址
 *
 * @return string
 */
function get_ip() {
    if (isset($_SERVER)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $realip = $_SERVER['REMOTE_ADDR'];
        }
    } else {
        if (getenv("HTTP_X_FORWARDED_FOR")) {
            $realip = getenv( "HTTP_X_FORWARDED_FOR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $realip = getenv("HTTP_CLIENT_IP");
        } else {
            $realip = getenv("REMOTE_ADDR");
        }
    }
    $realips  = explode(',',$realip);
    if(count($realips)>1){
        $realip = $realips[0];
    }
    wlog($realips,'ip测试2',4);
    return $realip;
}

/**
 * 根据IP获取地址详情  （百度地图 ----开放api）
 *
 * @param string $ip
 * @return bool|mixed
 */
function get_ip_address_info($ip = '') {
    $ip = $ip?$ip:get_ip();
    $ak = '6KwQ92bIRFPW1u58ZlfQhiiL0B5wGhGD';
    $url = file_get_contents("http://api.map.baidu.com/location/ip?ip=$ip&ak=$ak");
    $res1 = json_decode($url,true);
    $data =$res1;
    return $data;
}


function  get_ip_address_info_ali($ip=''){
    $ip = $ip?$ip:get_ip();
    $host = "http://ipquery.market.alicloudapi.com";
    $path = "/query";
    $method = "GET";
    $appcode = "58acb51aede444359ba7fa8e5dd3ee82";
    $headers = array();
    array_push($headers, "Authorization:APPCODE " . $appcode);
    $querys = "ip={$ip}";
    $bodys = "";
    $url = $host . $path . "?" . $querys;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER,false);
    if (1 == strpos("$".$host, "https://"))
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    $result  = json_decode(curl_exec($curl),true);
    if($result['ret']=='200'){
        return  $result['data']  ;
    }else{
        return  [];
    }
}











//------------------- 根据ip  获取所在城市 end-----------------

//是否是json
function is_json($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}


/**
 * @param $array
 * @param $key
 * @param string $type
 * @return bool
 * 判断数组键是否存在  或者  为空
 */
function array_key_check($array,$key,$type='empty'){
    //空的检测
    if($type=='empty'){
        if(isset($array[$key])&&(trim($array[$key])||$array[$key]===0||$array[$key]==='0')){
            return  true;
        }
    }
    //存在的检测
    if($type=='exist'){
        if(isset($array[$key])){
            return  true;
        }
    }
    return  false;
}

/**
 * @param $time
 * @return int
 * 时间状态设置
 */
 function time_status($time){
       if(is_string($time)){
           $time = explode(",",$time);
       }
       $status   = 0;
       $now_time = date('Y-m-d H:i:s');
       if($now_time<$time[0]){
             $status  = 0;
       }
       if($now_time>=$time[0]&&($now_time<=$time[1])){
             $status  = 1;
       }
       if($now_time>$time[1]){
            $status   = 2;
       }
       return $status;
 }


//json 数据   转   数组
function  json_to_array($data=''){
    $array_result  =  json_decode($data,true);
    if(is_string($array_result)){
        $string_result    =  trim($array_result,"[,],' '");
        $array_result     =  explode(',',$string_result);
    }
    return $array_result;
}



/**
 * @param $v
 */
function dd($v) {
      echo  '<pre>';
      print_r($v);
      exit;
}
