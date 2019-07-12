<?php

/**
 * 用户获奖经历管理
 * @author Administrator
 *
 */

namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;

class Award extends BaseController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * 获奖列表
     *
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function lists() {
        $uid = app()->request()->params('uid', $this->uid);
        if (empty($uid)) {
            throw new ParamsInvalidException("用户uid必须");
        }
        $id = app()->request()->params('id', 0);
        $user_lib = new \Lib\User\User();
        $lists = $user_lib->getAwardList($uid, $id);

        if (empty($lists)) {
            $lists = [];
        }

        $this->responseJSON($lists);
    }

    /**
     * 添加获奖经历
     *
     * @throws ServiceException
     */
    public function post() {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        $user_lib = new \Lib\User\User();
        $resMall = $user_lib->awardPost($params);

        $this->responseJSON(['id' => $resMall]);
    }

}
