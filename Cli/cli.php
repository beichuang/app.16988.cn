<?php
use Framework\Core;
/**
 * 从CLI执行
 * 用法示例： E:\php_workspace\blim\bin>php Cli.php Welcome/index name/john/age/24/sex/1
 * 1、“php的路径”
 * 2、“控制器的命名空间（不含controller顶级空间）”.“/”.“控制器方法”
 * 3、参数/参数值 参数存到变量$_GET中
 */
$BASE_PATH=dirname(__DIR__);
require $BASE_PATH.'/Framework/Bootstrap.php';
require_once $BASE_PATH.'/Framework/Helper/Fun.php';
require $BASE_PATH . '/vendor/autoload.php';

$param = isset($_SERVER ['argv'] [2]) ? $_SERVER ['argv'] [2] : '';
$arr = explode("/", $param);
for($i = 0; $i < count($arr); $i = $i + 2){
    if(isset($arr[$i])){
        $_GET [$arr [$i]] = isset($arr [($i + 1)])?$arr [($i + 1)]:"";
    }
}

$path = isset($_SERVER ['argv'] [1]) ? $_SERVER ['argv'] [1] : '';
$pos = strrpos($path, '/');
$method = substr($path, $pos + 1);

$ctlName = "\\Cli\\" . substr($path, 0, $pos);
$ctlName = str_replace("/", "\\", $ctlName);
$ref = new ReflectionMethod($ctlName, $method);
$ref->invoke(new $ctlName(), $method);

function msg() {
    $args = func_get_args(0);
    echo date('m-d H:i:s'), '>> ', implode(' ', $args), PHP_EOL;
}

function get_param($key, $default = "")
{
    if (isset($_GET [$key])){
        return $_GET [$key];
    } else{
        return $default;
    }
}

function cli_log($data,$exception,$fileName,$level=\Framework\Log::NOTICE)
{
    if ($exception && $exception instanceof \Exception) {
        $data['errorException']=array(
            'Code'=>$exception->getCode(),
            'File'=>$exception->getFile(),
            'Line'=>$exception->getLine(),
            'Message'=>$exception->getMessage(),
            'TraceAsString'=>$exception->getTraceAsString(),
        );
    }
    wlog($data,$fileName,$level);
}