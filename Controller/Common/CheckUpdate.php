<?php
namespace Controller\Common;

use Lib\Base\BaseController;

/**
 * 检查版本等
 *
 * @author yangzongxun
 *        
 */
class CheckUpdate extends BaseController
{

    /**
     * 检查Android，app版本
     *
     * @return string
     */
    public function android()
    {
        $current=app('mysqlbxd_mall_common')->fetch("select * from app_version where clientType='android' order by id desc limit 1");
        $clientVersionCode = $this->app->request->params('currentVersion', '');
        if(intval($clientVersionCode)>=intval($current['versionCode'])){
            $current=null;
        }
        if($current && $current['updateTime']){
            $current['updateTime']=date('Y-m-d',strtotime($current['updateTime']));
        }
        $this->responseJSON($current);
    }

    /**
     * 检查ios,app版本
     *
     * @return string
     */
    public function ios()
    {
        $this->responseJSON('');
    }
}
