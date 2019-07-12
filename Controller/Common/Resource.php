<?php

namespace Controller\Common;

use Lib\Base\BaseController;

class Resource extends BaseController {

    private $adLib = null;

    public function __construct() {
        parent::__construct();
        $this->adLib = new \Lib\Mall\Ad();
    }

    public function html()
    {
        $uri = app()->request()->getResourceUri();
        preg_match("/^(.+)\.html$/", $uri, $m);
        $uri = $m[1];

        $templateRootDir = app()->baseDir . DIRECTORY_SEPARATOR . "Template";
        if (!file_exists($templateRootDir . DIRECTORY_SEPARATOR . $uri . '.phtml')) {
            app()->halt('404', '请求的资源不存在');
        }
        $data = array(
            'CDN_BASE_URL_RES' => config('app.CDN.BASE_URL_RES'),
            'DOMAIN_APP' => get_request_url_schema() . '://' . config('app.baseDomain'),
            'wxJsConfig'=>$this->getWxJsSdkConfig(),
        );
        app()->render($uri, $data);
    }

    public function getWxJsSdkConfig()
    {
        $jssdk=new \Lib\Wx\JsSdk();
        $wxJsConfig=$jssdk->getJsSdkPageInitSign();
        return $wxJsConfig;
    }

}
