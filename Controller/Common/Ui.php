<?php

namespace Controller\Common;

use Lib\Base\BaseController;

class Ui extends BaseController {

    /**
     * 是否显示某个组件
     * @return 0 或 1
     */
    public function isShow()
    {
        $showList=[
            'baidu_mini_goods_detail_custom_service_button'=>0
        ];
        $id=app()->request()->params('id');
        $isShow=1;
        if($id && isset($showList[$id])){
            $isShow=$showList[$id];
        }
        $this->responseJSON($isShow);
    }

}
