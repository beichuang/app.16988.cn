<?php
namespace Lib\Mall;

class Express extends MallBase
{

    /**
     * 快递公司列表
     */
    public function companyList($params)
    {
        return $this->passRequest2Mall($params, 'mall/express/company/list');
    }
}
