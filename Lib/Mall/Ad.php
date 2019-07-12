<?php
namespace Lib\Mall;

class Ad extends MallBase
{

    /**
     * 广告列表
     */
    public function lists($params)
    {
        return $this->passRequest2Mall($params, 'mall/ad/list');
    }

    /** 首页广告列表
     * @param $params
     * @return mixed
     */
    public function adLog($params)
    {
        return $this->passRequest2Mall($params, 'mall/ad/log');
    }
}
