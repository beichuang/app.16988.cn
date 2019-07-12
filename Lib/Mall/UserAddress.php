<?php
namespace Lib\Mall;

class UserAddress extends MallBase
{

    /**
     * 新增或修改收货地址
     */
    public function add($params)
    {

        return $this->passRequest2Mall($params, 'mall/user/address/post');
    }

    /**
     * 收货地址列表
     */
    public function lists($params)
    {
        return $this->passRequest2Mall($params, 'mall/user/address/list');
    }
}
