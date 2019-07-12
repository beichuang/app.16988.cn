<?php
namespace Middleware;

use Framework\Core;
use Lib\Common\SessionKeys;
use Exception\AccessException;

/**
 * 参数校验类
 * 参数做了签名处理，在此处做签名校验
 */
class SignCheck extends \Framework\Middleware
{

    public function call()
    {
//         if (! $this->isCommonAction()) {
//             if(!app()->request()->isAjax()){
//                 $this->checkSignature();
//             }
//         }
        $this->next->call();
    }

    /**
     * 当前请求是否是公共请求
     *
     * @return boolean
     */
    private function isCommonAction()
    {
        $uri = $this->app->request->getPath();
        $uri = trim($uri, "/");
        $arr = explode("/", $uri);
        $method = array_pop($arr);
        if (preg_match("/.*\.html$/", $method)) {
            return true;
        }
        $method = get_static_resource_control_method($method);
        $uri = strtolower(implode("/", $arr)) . "/" . $method;
        $commonActions = config("app.accessSignCheck.interface_common");
        foreach ($commonActions as $commonAct) {
            if (strpos($uri, $commonAct) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查签名
     * http头
     * XjHandp-Nonce-String=【10位随机字符串（a-zA-Z0-9）】【毫秒时间戳】
     * XjHandp-Sign=MD5（【10位随机字符串（a-zA-Z0-9）】【毫秒时间戳】【签名key】，小写 ）
     *
     * @return boolean
     */
    private function checkSignature()
    {
        $appsecret = config('app.accessSignCheck.secret', '');
        $signature = app()->request()->headers('XjHandp-Sign', '');
        $nonceStr = app()->request()->headers('XjHandp-Nonce-String', '');
        if (! $signature || ! $nonceStr) {
            throw new AccessException("非法请求", AccessException::CODE_SIGN_CHECK_FAIL);
        }
        if (strtolower(md5($nonceStr . $appsecret)) != $signature) {
            throw new AccessException("签名校验失败", AccessException::CODE_SIGN_CHECK_FAIL);
        }
    }
}
