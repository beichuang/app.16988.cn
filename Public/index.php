<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token');

ini_set("session.cookie_httponly", 1);
ini_set('date.timezone','Asia/Shanghai');
/**
 * 这是项目的入口文件，也是路由文件
 *
 */
require '../Framework/Bootstrap.php';
require_once '../Framework/Helper/Fun.php';
require '../vendor/autoload.php';
register_shutdown_function(function(){
    $error=error_get_last();
    if ($error && is_array($error)){
        wlog($error,'fatal-shutdown-error');
    }
});

// 事件
// \Event\Events::register();
// $time2=microtime(true)*1000;
// 开启session
//start_session();

app()->map('/thirdparty/pay/notify/:method', [new Lib\Pay\Pay(),'handlerThirdpartyPayNoticeRouter'])->via('GET', 'POST');
app()->map('/html/infoDetail.html', [new \Controller\News\News(),'detail'])->via('GET', 'POST');

app()->map('/:uri+/', function($path) {
    if (!is_array($path) || count($path) < 2) {
        throw new \Exception\AccessException("请求的资源不存在", \Exception\AccessException::CODE_RESOURCE_NOT_EXISTS);
    }
    $pc_site = ['toutiao' => 'Headlines', 'xunbao' => 'Treasure', 'quan' => 'Circle', 'app' => 'App', 'about' => 'About', 'search' => 'Search'];
    if (array_key_exists($path[0], $pc_site)) {
        $ctrlName = "\\Controller\\Site\\" . $pc_site[$path[0]];
        $method = str_replace('.html', '', $path[1]);
        app()->view->set('action', $path[0]);
        $ref = new ReflectionMethod($ctrlName, $method);
        $ref->invoke(new $ctrlName(), $method);
    } else {
        $method = array_pop($path);

        // 解析静态资源，以html结尾
        if ($path[0] == 'html' && preg_match("/.*\.(html)$/", $method, $match)) {
            $ctlObj = new \Controller\Common\Resource();
            $extension = isset($match[1]) ? $match[1] : '';
            if (!$extension || !method_exists($ctlObj, $extension)) {
                app()->halt('404', '请求的资源不存在');
            }
            $ctlObj->$extension();
            return;
        }

        $path = array_map("ucwords", $path);
        $action = implode("\\", $path);
        $ctlName = "\\Controller\\" . $action;
        $ctlMethod = $method; //get_static_resource_control_method($method);
        if (!$ctlMethod) {
            throw new \Exception\AccessException("请求的资源不存在,m:{$method}", \Exception\AccessException::CODE_RESOURCE_NOT_EXISTS);
        }
        if (!class_exists($ctlName)) {
            throw new \Exception\AccessException("请求的资源不存在,a:{$action}", \Exception\AccessException::CODE_RESOURCE_NOT_EXISTS);
        }
        $ctlObj = new $ctlName();
        if (!method_exists($ctlObj, $ctlMethod)) {
            throw new \Exception\AccessException("请求的资源不存在,a:{$action},m:{$method}", \Exception\AccessException::CODE_RESOURCE_NOT_EXISTS);
        }
        $ctlObj->$ctlMethod();
    }
})->via('GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS');

app()->add(new \Middleware\AccessCheck());
app()->add(new \Middleware\SignCheck());
app()->add(new \Middleware\ExceptionHandler());
app()->run();
