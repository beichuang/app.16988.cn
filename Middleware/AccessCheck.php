<?php
namespace Middleware;

use Framework\Core;
use Framework\Helper\SessionHelper;
use Lib\Common\SessionKeys;
use Exception\AccessException;

/**
 * 权限用户登录状态类
 */
class AccessCheck extends \Framework\Middleware
{

    public function call()
    {
        $this->checkAppVersion();
        if (! $this->isCommonAction()) {
            $this->auth();
        }
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
        if(!$uri){
            return true;
        }
        $arr = explode("/", $uri);
        $method = array_pop($arr);
        if (preg_match("/.*\.html$/", $method)) {
            return true;
        }
        $method = get_static_resource_control_method($method);
        $uri = implode("/", $arr) . "/" . $method;
        $commonActions = config("app.interface_common");
        $uri=strtolower($uri);
        foreach ($commonActions as $commonAct) {
            $commonAct=strtolower($commonAct);
            if (strpos($uri, $commonAct) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 认证
     *
     * @throws \Exception\AccessException
     * @return boolean
     */
    private function auth()
    {
        // return true;
        if (! SessionHelper::exists(SessionKeys::USER_ID) || !SessionHelper::get(SessionKeys::USER_ID)) {
            throw new \Exception\AccessException('未登录', \Exception\AccessException::CODE_USER_NOT_LOGIN);
        }
    }

    /**
     * 检查APP版本
     *
     * [User-Agent] => JUPAIAPP/1.0_1/Android/6.0/Android SDK built for x86_64//channelnull
     *
     * @throws \Exception\AccessException
     */
    private function checkAppVersion()
    {
        // [User-Agent] => JUPAIAPP/1.0_1/Android/6.0/Android SDK built for x86_64//channelnull
        $userAgent = app()->request()->getUserAgent();
        if (preg_match("/JUPAIAPP\/(.+)_(\d+)\/.*/", $userAgent, $m)) {
            $minVersionNum = config('app.limit.app.minVersionNum', 0);
            $version = isset($m[1]) ? $m[1] : '';
            $versionNum = isset($m[2]) ? $m[2] : 0;
            if ($versionNum < $minVersionNum) {
                throw new \Exception\AccessException('版本过低', \Exception\AccessException::CODE_APP_VERSION_TOO_LOW);
            }
        }
    }
}
