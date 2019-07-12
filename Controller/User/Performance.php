<?php

/**
 * 业绩
 * @author Administrator
 *
 */

namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;

class Performance extends BaseController {

    private $goodsLib;
    public function __construct() {
        parent::__construct();
        $this->goodsLib = new \Lib\Mall\Goods();
    }

    /**
     * 业绩列表
     *
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function lists() {
        $params = app()->request()->params();
        $params['page'] = app()->request()->params('page',1);
        $params['pageSize'] = app()->request()->params('pageSize',10);

        $uid = app()->request()->params('uid', $this->uid);
        if (empty($uid)) {
            throw new ParamsInvalidException("用户uid必须");
        }
        $params['uid'] = $uid;

        $type = app()->request()->params('type',1);
        $month = app()->request()->params('month',date('Y-m',time()));
        if ($type == 1){
            //今日业绩
            $params['startTime'] = date('Y-m-d',time()).' 00:00:00';
            $params['endTime'] = date('Y-m-d',time()).' 23:59:59';
            $params['action'] = 'query';
            $params['level'] = 1;  //只查看一级账户

            $sales_amount = $distribution_amount = $count = 0;
            $lists = $this->goodsLib->performance($params);
            if ($lists['count']>0) {
                $order_lib = new \Lib\Mall\Order();
                foreach ($lists['list'] as &$val){
                    $order = $order_lib->detail(['sn'=>$val['o_sn']]);
                    if($order['o_status'] != 0){
                        if (in_array($order['o_status'],[1,2,3])){
                            $sales_amount += $val['o_price'];
                            $distribution_amount += $val['dl_price'];
                            $count += 1;
                            $val['type'] = '冻结中';
                        }elseif (in_array($order['o_status'],[14,15,25])){
                            $sales_amount += $val['o_price'];
                            $distribution_amount += $val['dl_price'];
                            $count += 1;
                            $val['type'] = '退款中';
                        }elseif (in_array($order['o_status'],[24,35])){
                            $val['type'] = '已退款';
                        }elseif (in_array($order['o_status'],[100])){
                            $val['type'] = '已入账';
                        }

                        $val['g_name'] = $order['g_name'];
                        $val['o_payDate'] = $order['o_payDate'];
                    }

                    /*$sales_amount += $val['o_price'];
                    $distribution_amount += $val['dl_price'];

                    if($val['dl_status'] == 1){
                        $val['type'] = '冻结中';
                    }else if($val['dl_status'] == 2){
                        $val['type'] = '已入账';
                    }

                    $order = $order_lib->detail(['sn'=>$val['o_sn']]);
                    $val['g_name'] = $order['g_name'];
                    $val['o_payDate'] = $order['o_payDate'];*/
                }
            }
            $lists['count'] = $count;
            $lists['sales_amount'] = $sales_amount;
            $lists['distribution_amount'] = $distribution_amount;
        }elseif ($type == 2) {
            //个人业绩
            $params['month'] = $month;
            $params['action'] = 'query';
            $params['level'] = 1;  //只查看一级账户

            $sales_amount = $distribution_amount = $count = 0;
            $lists = $this->goodsLib->performance($params);
            if ($lists['count']>0) {
                $order_lib = new \Lib\Mall\Order();
                foreach ($lists['list'] as &$val){
                    $order = $order_lib->detail(['sn'=>$val['o_sn']]);
                    if($order['o_status'] != 0){
                        if (in_array($order['o_status'],[1,2,3])){
                            $sales_amount += $val['o_price'];
                            $distribution_amount += $val['dl_price'];
                            $count += 1;
                            $val['type'] = '冻结中';
                        }elseif (in_array($order['o_status'],[14,15,25])){
                            $sales_amount += $val['o_price'];
                            $distribution_amount += $val['dl_price'];
                            $count += 1;
                            $val['type'] = '退款中';
                        }elseif (in_array($order['o_status'],[24,35])){
                            $val['type'] = '已退款';
                        }elseif (in_array($order['o_status'],[100])){
                            $val['type'] = '已入账';
                        }

                        $val['g_name'] = $order['g_name'];
                        $val['o_payDate'] = $order['o_payDate'];
                    }
                }
            }
            $lists['count'] = $count;
            $lists['sales_amount'] = $sales_amount;
            $lists['distribution_amount'] = $distribution_amount;
        }else{
            //先找团队
            $inviteModel = new \Model\User\Invite();
            $result = $inviteModel->lists(['u_id'=>$uid,'uil_is_register'=>1],1,200);

            $total_sales_amount = $total_distribution_amount = $total = 0;
            $arrs = [];
            if ($result[1]) {
                $userLib = new \Lib\User\User();

                foreach ($result[0] as $info) {
                    if ($info['uil_phone']) {
                        $users = $userLib->getUserInfo([], $info['uil_phone']);
                        if($users){
                            $params['uid'] = current($users)['u_id'];
                            $params['month'] = $month;
                            $params['status'] = 2; //已入账
                            $params['action'] = 'query';
                            $list = $this->goodsLib->performance($params);
                            if($list['list']){
                                $arr = [];
                                $sales_amount = $distribution_amount = 0;
                                foreach($list['list'] as $val){
                                    $sales_amount += $val['o_price'];
                                    $distribution_amount += $val['dl_price'];
                                }
                                $arr['phone'] = substr_replace(current($users)['u_phone'], '****', 3, 4);;
                                $arr['sales_amount'] = $sales_amount;
                                $arr['distribution_amount'] = $distribution_amount;
                                $arrs[] = $arr;
                            }
                        }
                        $total += $list['count'];
                        $total_sales_amount += $sales_amount;
                        $total_distribution_amount += $distribution_amount;
                    }
                }
            }
            $lists['count'] = $total;
            $lists['sales_amount'] = $total_sales_amount;
            $lists['distribution_amount'] = $total_distribution_amount;
            $lists['list'] = $arrs;
        }

        $this->responseJSON($lists);
    }

}
