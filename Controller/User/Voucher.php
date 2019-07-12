<?php

/**
 * 代金券
 * @author Houbotao
 */

namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ParamsInvalidException;

class Voucher extends BaseController {
    public $voucherLib;
    public function __construct() {
        parent::__construct();
        $this->voucherLib = new \Lib\Mall\Voucher();
    }

    /**
     * 用户获取的代金券列表
     * @param type $page
     * @param type $pageSize
     * @throws ParamsInvalidException
     */
    public function lists() {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        $params['status'] = (int) app()->request()->params('status');
        if (!$params['uid']) {
            throw new ParamsInvalidException("未登录");
        }

        $result = $this->voucherLib->lists($params);
        $this->responseJSON($result);
    }

    /**
     * 领取代金券
     * @throws ParamsInvalidException
     * @throws ModelException
     */
    public function receive() {
        $sn = strtoupper(app()->request()->params('sn'));
        $tid = app()->request()->params('tid');
        $uid = $this->uid;
        if ($uid <= 0) {
            throw new ParamsInvalidException("缺少参数");
        }
        if ($sn || $tid > 0) {
            if ($sn) {
                $result = $this->voucherLib->receive(['uid' => $uid, 'sn' => $sn]);
            } elseif ($tid) {
                $result = $this->voucherLib->receive(['uid' => $uid, 'tids' => $tid]);
            }
        } else {
            throw new ParamsInvalidException("参数错误");
        }
        $this->responseJSON($result);
    }

    /**
     * 得到可用代金券列表
     * @throws ParamsInvalidException
     */
    public function availableVoucher() {
        $params = app()->request()->params();
        $uid = $this->uid;
        $goods_total = (float) app()->request()->params('goods_total');
        $goods_ids = app()->request()->params('goods_ids', '');
        if($goods_total){
            if (!($uid && $goods_ids)) {
                throw new ParamsInvalidException("缺少参数");
            }
            $params['uid'] = $uid;
            $params['status'] = 0;
            $params['time'] = date('Y-m-d H:i:s');
            $params['goods_total'] = $goods_total;
            $params['goods_ids'] = $goods_ids;
            $result = $this->voucherLib->lists($params);
            $voucher_lists = ['count' => $result['enabled_count'], 'list' => $result['enabled_list']];
        }else{
            $voucher_lists = ['count' => 0, 'list' => []];
        }
        $this->responseJSON($voucher_lists);
    }

    /**
     * 得到不可用代金券列表
     * @throws ParamsInvalidException
     */
    public function disabledtVoucher() {
        $params = app()->request()->params();
        $uid = $this->uid;
        $goods_total = (float) app()->request()->params('goods_total');
        $goods_ids = app()->request()->params('goods_ids', '');
        if (!($uid && $goods_total && $goods_ids)) {
            throw new ParamsInvalidException("缺少参数");
        }
        $params['uid'] = $uid;
        $params['status'] = 0;
        $params['time'] = date('Y-m-d H:i:s');
        $params['goods_total'] = $goods_total;
        $params['goods_ids'] = $goods_ids;
        $result = $this->voucherLib->lists($params);
        $voucher_lists = ['count' => $result['disable_count'], 'list' => $result['disable_list']];
        $this->responseJSON($voucher_lists);
    }

    /**
     * 微信公众号购买成功之后，给购买人发放代金券
     */
    public function getAwardVoucher(){
        $uid = app()->request()->params('uid',$this->uid);

        $tids = api_request(['skey' => 'user_award_voucher_template_id'], 'mall/setting');
        if ($tids) {
            $voucherLib = new \Lib\Mall\Voucher();
            $res = $voucherLib->receive(['uid' => $uid, 'tids' => $tids]);
        }

        $this->responseJSON($res);
    }
}
