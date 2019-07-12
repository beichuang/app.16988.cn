<?php

namespace Lib\Base;

use \Framework\Controller;
use Framework\Helper\SessionHelper;
use Lib\Common\SessionKeys;

class BaseController extends Controller {

    protected $app = null;
    protected $userInfo = null;
    protected $uid = null;
    /**
     * @var string android ios h5 h5-android h5-ios h5-wx
     */
    protected $clientType='';
    const CLIENT_TYPE_ANDROID='android';
    const CLIENT_TYPE_IOS='ios';
    const CLIENT_TYPE_H5='h5';

    public function __construct() {
        $this->app = app();
        $this->uid = SessionHelper::exists(SessionKeys::USER_ID) ? SessionHelper::get(SessionKeys::USER_ID) : null;
        $source=app()->request()->headers('Source');
        if(strtolower($source)==self::CLIENT_TYPE_ANDROID){
            $this->clientType=self::CLIENT_TYPE_ANDROID;
        }elseif (strtolower($source)==self::CLIENT_TYPE_IOS){
            $this->clientType=self::CLIENT_TYPE_IOS;
        }else{
            $this->clientType=self::CLIENT_TYPE_H5;
        }
    }

    protected function checkUser($uid) {
        $userLib = new \Lib\User\User();
        $userResult = $userLib->getUserInfo([$uid], [], 1);
        $userInfo = current($userResult);
        if ($userInfo['u_status'] === '1') {
            throw new \Exception\ParamsInvalidException("用户被禁用");
        }
    }

    protected function checkUserStatus($uid) {
        $userLib = new \Lib\User\User();
        $userResult = $userLib->getUserInfo([$uid], [], 1);
        $userInfo = current($userResult);
        if ($userInfo['u_status'] === '3') {
            if ($userInfo['u_status_expiry'] > 0 && $userInfo['u_status_expiry'] > time()) {
                throw new \Exception\ParamsInvalidException("用户禁言至（" . date('Y-m-d H:i', $userInfo['u_status_expiry']) . "）");
            } else {
                throw new \Exception\ParamsInvalidException("用户永久禁言");
            }
        }
        if ($userInfo['u_status'] === '4') {
            if ($userInfo['u_status_expiry'] > 0 && $userInfo['u_status_expiry'] > time()) {
                throw new \Exception\ParamsInvalidException("用户禁止访问至（" . date('Y-m-d H:i', $userInfo['u_status_expiry']) . "）");
            } else {
                throw new \Exception\ParamsInvalidException("用户永久禁止访问");
            }
        }
    }

    protected function responseJSON($data, $error_type = 0, $error_code = 0, $error_msg = "") {
        $app = app();
        $res['error_type'] = $error_type;
        $res['error_code'] = $error_code;
        $res['error_msg'] = $error_msg;
        $res['data'] = $data;
        // $res=null_value_to_empty_string_filter($res);
        if (is_jsonp_request()) {
            jsonp_response($res);
        } else {
            $app->response()->withJson($res);
        }
    }

    protected function generateRequestId($prefix = '') {
        $uniqid = uniqid($prefix);
        while ($uniqid == uniqid($prefix)) {
            $uniqid = uniqid($prefix);
        }
        return $uniqid;
    }

    /**
     * 验证一个值
     *
     * @param 要验证的值 $value            
     * @param 验证规则 $rule
     *            目前有，require、email、url、currency、number、zip、integer、double、english
     * @throws \Exception
     */
    protected function check($paramName, $value, $rule) {
        $validate = array(
            'require' => '/\S+/',
            'email' => '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
            'url' => '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(:\d+)?(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/',
            'currency' => '/^\d+(\.\d+)?$/',
            'number' => '/^\d+$/',
            'zip' => '/^\d{6}$/',
            'integer' => '/^[-\+]?\d+$/',
            'double' => '/^[-\+]?\d+(\.\d+)?$/',
            'english' => '/^[A-Za-z]+$/'
        );
        // 检查是否有内置的正则表达式
        if (isset($validate[strtolower($rule)])) {
            $regex = $validate[strtolower($rule)];
            if (preg_match($regex, $value) !== 1) {
                return false;
            }
        } else {
            return false;
        }
        return true;
    }

    /**
     * PC端页面配置信息
     */
    protected function pcSiteTitle()
    {
        $data = array(
            'CDN_BASE_URL_RES' => config('app.CDN.BASE_URL_RES'),
            'SITE_NAME' => '掌玩',
            'SITE_URL' => '//' . config('app.baseDomain'),
            'SITE_TITLE' => '专业的艺术品电商交易平台-精选书画、陶瓷、文玩艺术品！',
            'SITE_KEYWORDS' => '掌玩,艺术品,书画,陶瓷,文玩,艺术品交易平台',
            'SITE_DESCRIPTION' => '掌玩，致力于打造成为国内最大的艺术品交易平台；汇集书画、陶瓷、文玩等多个艺术品类，为各类艺术机构、艺术家以及收藏爱好者提供一个作品展售与信息交流的交互平台。',
        );
        foreach ($data as $k => $v) {
            app()->view->set($k, $v);
        }
    }

}
