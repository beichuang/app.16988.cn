<?php

namespace Lib\Mall;

class Auction extends MallBase {

    /**
     * 拍品列表
     */
    public function lists($params) {
        $resMall = $this->passRequest2Mall($params, 'mall/auction/list');
        return $resMall;
    }

    /**
     * 参与的拍品列表
     */
    public function involveLists($params) {
        $resMall = $this->passRequest2Mall($params, 'mall/auction/involve/list');
        return $resMall;
    }

    /**
     * 查询详细
     */
    public function detailGet($params) {
        return $this->passRequest2Mall($params, 'mall/auction/detail/get');
    }

    /**
     * 查询出价记录
     */
    public function getBidRecord($params) {
        return $this->passRequest2Mall($params, 'mall/auction/bidrecord/get');
    }

    /**
     * 新增出价记录
     */
    public function addBidRecord($params) {
        return $this->passRequest2Mall($params, 'mall/auction/bidrecord/add');
    }

    /**
     * 新增/修改商品
     */
    public function itemSave($params) {
        return $this->passRequest2Mall($params, 'mall/auction/save');
    }
}
