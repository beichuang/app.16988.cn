<?php

/**
 * 保证金
 */

namespace Controller\Mall\Order;

use Exception\ServiceException;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Rest\Mall\PledgeManager;

class Pledge extends BaseController {

    private $goodsLib = null;
    private $orderLib = null;

    public function __construct() {
        parent::__construct();
    }


    /**
     * 1. 保证金列表接口
     */
    public function pledgeList()
    {
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 20);

        $uId = $this->uid;
//        $uId = '949081942';
        if (!$uId) {
            throw new \Exception\ParamsInvalidException("请登录");
        }
        $data = PledgeManager::lists($uId, $page, $pageSize);

        $this->responseJSON($data);
    }

    /**
     * 2. 保证金违约总金额
     */
    public function amount()
    {
        $uId = $this->uid;
        //$uId = '949081942';
        if (!$uId) {
            throw new \Exception\ParamsInvalidException("请登录");
        }
        $breakPromiseAmount = PledgeManager::breakPromise($uId, 3);
        $balanceAmount = PledgeManager::breakPromise($uId, 1);


        $data = [
            'breakPromiseAmount' => $breakPromiseAmount['total'],
            'balanceAmount' => $balanceAmount['total'],
        ];

        $this->responseJSON($data);
    }



}
