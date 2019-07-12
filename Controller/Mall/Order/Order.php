<?php

/**
 * 订单
 *
 */

namespace Controller\Mall\Order;

use Exception\ServiceException;
use Framework\Helper\FileHelper;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Lib\Common\Region;
use Model\Mall\Goods;
use Rest\Mall\Facade\LogManager;
use Rest\Mall\Facade\OrderManager;
use Rest\Mall\Facade\WorkOrderManager;

class Order extends BaseController {

    private $goodsLib = null;
    private $orderLib = null;

    public function __construct() {
        parent::__construct();
        $this->goodsLib = new \Lib\Mall\Goods();
        $this->orderLib = new \Lib\Mall\Order();
        $this->certificationModel = new \Model\User\Certification();
    }

    /**
     * 订单评论信息
     */
    public function comments()
    {
        $o_id = app()->request()->params('o_id', 1);
        //订单评价
        $orderComment=null;
        if($orderCommentInfo=app('mysqlbxd_mall_user')->fetch('select * from `order_comment` where o_id='.$o_id)){
            $orderComment=$orderCommentInfo;
        }
        $goodsComment=app('mysqlbxd_app')->select('select g_id,gc_content,gc_score from goods_comment where o_id='.$o_id);
        $val['orderComment']=$orderComment;
        $val['orderGoodsComment']=$goodsComment?$goodsComment:[];
        $this->responseJSON($val);
    }
    /**
     * 按uid查询订单列表
     */
    public function lists() {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        $params['listType'] = app()->request()->params('listType', 1);   //1=买家  0=卖家
        $params['isExchangeIntegral'] = 0;
        if(isset($params['status'])) {
            //临时解决APP端已完成订单，传递参数为3的问题
            if (strpos($params['status'] . ',', '3,') !== false) {
                $params['status'] .= ',100';
            }
        }
        $resMall = $this->orderLib->lists($params);

        if ($params['listType'] == 1){
            $uid = 'o_salesUid';
        }else {
            $uid = 'u_id';
        }
        $userLib = new \Lib\User\User();
        $userLib->extendUserInfos2Array($resMall, $uid, array(
            'u_nickname' => 'o_nickname',
            'u_realname' => 'o_realname',
            'u_avatar' => 'o_avatar',
            'ue_imId' => 'ue_imId',
            'ue_imPassword' => 'ue_imPassword',
        ));
        foreach ($resMall as $key => &$val) {
            if (empty($val['o_realname'])){
                $val['o_realname'] = $val['o_nickname'];
            }

            $val['o_provinceName'] = \Lib\Common\Region::getRegionNameByCode($val['o_provinceCode']);
            $val['o_cityName'] = \Lib\Common\Region::getRegionNameByCode($val['o_cityCode']);
            $val['o_areaName'] = \Lib\Common\Region::getRegionNameByCode($val['o_areaCode']);

            if ($params['listType'] == 1){
                $val['certification'] = $this->certificationModel->getType($val['o_salesUid']);
            }else {
                $val['certification'] = $this->certificationModel->getType($val['u_id']);
            }
            //订单评价
            $isOrderCommented=false;
            if(in_array($val['o_status'],[3,100])
            && app('mysqlbxd_mall_user')->fetchColumn('select count(*) c from `order_comment` where o_id='.$val['o_id'])
            ){
                $isOrderCommented=true;
            }
            $val['isOrderCommented']=$isOrderCommented;

            if($val['g_type'] == 4 || $val['g_type'] == 7) {
                $orderStatus = $this->orderStatus($val);
                $val['o_flag'] = $orderStatus['o_flag'];
                $val['sales_imId'] = $val['ue_imId'];
                $val['sales_imPassword'] = $val['ue_imPassword'];
            }else {
                //检测商品是否存在
                $rel = Goods::detailGet($val['g_id']);
                if ($rel) {
                    $goodsInfo = $this->goodsLib->detailGet(['id' => $val['g_id']]);

                    $val['g_width'] = $goodsInfo['item'][0]['g_width'];
                    $val['g_high'] = $goodsInfo['item'][0]['g_high'];
                    $val['g_material'] = $goodsInfo['item'][0]['g_material'];
                    $val['g_madeTime'] = $goodsInfo['item'][0]['g_madeTime'];
                    $val['categoryName'] = $goodsInfo['item'][0]['categoryName'];
                    $val['g_surfaceImg'] = $goodsInfo['item'][0]['g_surfaceImg'];

                    $orderStatus = $this->orderStatus($val);
                    $val['o_flag'] = $orderStatus['o_flag'];

                    $val['sales_imId'] = $val['ue_imId'];
                    $val['sales_imPassword'] = $val['ue_imPassword'];
                } else {
                    unset($resMall[$key]);
                }
                //待付款订单返回订单支付状态
                if($val['o_status'] == 0) {
                    $val['a_payRemainingSeconds'] = strtotime("{$val['o_createDate']}+30 minute") - time();
                }
            }
        }

        $userLib->extendUserInfos2Array($resMall, 'u_id', array(
            'ue_imId' => 'ue_imId',
            'ue_imPassword' => 'ue_imPassword',
        ));

        $this->responseJSON($resMall);
    }

    /**
     * 买家取消订单
     */
    public function cancel() {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        $resMall = $this->orderLib->cancel($params);
        $this->responseJSON($resMall);
    }

    /**
     * 买家申请退款/退货
     */
    public function refund() {

        //throw new ParamsInvalidException("请联系客服.");

        $params = app()->request()->params();
        $sn = $params['o_sn'];
        $type = $params['type'];
        $resMall = $this->orderLib->detail(['sn' => $sn, 'uid' => $this->uid]);

        if (!in_array($resMall['o_status'], [1, 2, 3])) {
            throw new ParamsInvalidException("该订单退状态不允许操作." . $resMall['o_status']);
        }

        if (!isset($sn) || empty($type)) {
            throw new ParamsInvalidException("缺少参数,请检查.");
        }

        if (!in_array($type, [1, 2])) {
            throw new ParamsInvalidException("type参数值不正确");
        }

        $refundModel = new \Model\Mall\Refund();
        $refundModel->beginTransaction();
        try {
            if (($resMall['o_status'] == 2) || ($resMall['o_status'] == 3)) {//走申请退货退款单时的流程
                $data = array(
                    'r_reason' => $params['r_reason'],
                    'r_content' => $params['r_content'],
                    'r_linkman' => $params['r_linkman'],
                    'r_tel' => $params['r_tel'],
                    'o_sn' => $sn,
                    'u_id' => $resMall['u_id'],
                    'g_saleid' => $resMall['o_salesUid'],
                    'r_status' => $type,
                    'r_images' => $params['r_images'],
                    'r_type' => $type,
                    'r_ctime' => date('Y-m-d H:i:s'),
                    'r_utime' => date('Y-m-d H:i:s'),
                );
                $refundModel->add($data);
            }

            $refundParams = [
                'id' => $resMall['o_id'],
                'type' => $type,
                'reason' => empty($params['r_reason']) ? $type : $params['r_reason'],
                'uid' => $this->uid,
                'refund_time' => date('Y-m-d H:i:s'),
            ];

            $resOrderRefund = $this->orderLib->refund($refundParams);
            $refundModel->commit();
        } catch (\Exception $e) {
            $refundModel->rollback();
            throw $e;
        }

        $this->responseJSON($resOrderRefund);
    }


    /**
     *  买家  ---- 取消申请退款/退货
     */
     public function  cancelRefund(){
         $params = app()->request()->params();
         $sn     = isset($params['o_sn'])?$params['o_sn']:'';
         $type   = isset($params['type'])?$params['type']:'';
         if (!isset($sn) || empty($type)) {
             throw new ParamsInvalidException("缺少参数,请检查.");
         }
         if (!in_array($type, [1, 2])) {
             throw new ParamsInvalidException("type参数值不正确");
         }
        $resMall = $this->orderLib->detail(['sn' => $sn, 'uid' => $this->uid]);
        if(!$resMall){
            throw new ParamsInvalidException('订单不存在');
        }
        try{
            //开启事务
            app('mysqlbxd_mall_user')->beginTransaction();
            app('mysqlbxd_mall_common')->beginTransaction();
            app('mysqlbxd_app')->beginTransaction();
            //1：  买家申请退货  卖家还未同意   买家就取消了
            if($type==1){
                if(!isset($resMall['o_status']) || ($resMall['o_status'] != 14)){
                    throw new  \Exception('当前订单状态不允许撤销');
                }
                //修改订单状态  重新修改回来
                $order['o_updateDate'] = date('Y-m-d H:i:s');
                $order['o_status'] = 1;
                $order['o_cancelReason'] = '';
                $order['refund_time'] = '';
                $update =  app('mysqlbxd_mall_user')->update('order',$order,['o_id' => $resMall['o_id']]);
                if(!$update){
                    throw new  \Exception('订单撤销失败,请重试');
                }
                //日志生成
                $addLog = LogManager::log($resMall['o_id'],116,"卖家还未发货,买家撤销申请退款o_id={$resMall['o_id']}",30000);
                if(!$addLog){
                    throw new  \Exception('撤销失败,请重试');
                }
            }

            // 2： 收到货以后  买家申请售后  卖家还未同意    买家就撤销了
            if($type==2){
                //2-1:   订单状态校验
                if(!isset($resMall['o_status']) || ($resMall['o_status'] != 15)){
                    throw new  \Exception('当前订单状态不允许撤销');
                }
                //2-2: 修改订单状态  重新修改回来
                $order['o_updateDate'] = date('Y-m-d H:i:s');
                $order['o_status'] = 3;
                $order['o_cancelReason'] = '';
                $order['refund_time'] = '';
                $update      = app('mysqlbxd_mall_user')->update('order',$order,['o_id' => $resMall['o_id']]);
                if(!$update){
                    throw new  \Exception('订单撤销失败,请重试');
                }
                //2-3:删除  refund表  退款记录
                $refund_sql    = "select r_id from  refund where o_sn='{$sn}' and  u_id={$resMall['u_id']}  and r_status={$type}  and r_type={$type} ";
                $checkRefundId   =  app('mysqlbxd_app')->fetchColumn($refund_sql);
                if(!$checkRefundId){
                    throw new  \Exception('撤销失败,请重试');
                }
                $delete  = app('mysqlbxd_app')->query("delete  from  refund  where r_id={$checkRefundId} ");
                if(!$delete){
                    throw new  \Exception('撤销失败,请重试');
                }
                //2-5 增加记录日志
                $addLog      = LogManager::log($resMall['o_id'],117,"卖家还未同意退货,买家取消退货申请o_id={$resMall['o_id']}",30000);
                if(!$addLog){
                    throw new  \Exception('撤销失败,请重试');
                }
            }
            app('mysqlbxd_mall_user')->commit();
            app('mysqlbxd_mall_common')->commit();
            app('mysqlbxd_app')->commit();
            $this->responseJSON('操作成功');
        }catch(\Exception $e){
            app('mysqlbxd_mall_user')->rollback();
            app('mysqlbxd_mall_common')->rollback();
            app('mysqlbxd_app')->rollback();
            throw new ParamsInvalidException($e->getMessage());
        }
     }


    /**
     * 卖家确认退款 xtype = 14/24,16/36
     */
    public function confirmRefund() {
        $params = app()->request()->params();
        $sn = $params['o_sn'];
        $resMall = $this->orderLib->detail(['sn' => $sn, 'salesUid' => $this->uid]);

        if (!in_array($resMall['o_status'], [14, 16])) {
            throw new ParamsInvalidException("该订单退状态不允许操作." . $resMall['o_status']);
        }

        $refundModel = new \Model\Mall\Refund();
        $goodsLib = new \Lib\Mall\Goods();
        $refundModel->beginTransaction();
        try {
            $retRefund = true;
            //支付金额大于0进行退款
            if ($resMall['o_pay'] > 0) {
                $refundMoney['o_sn'] = $sn;
                $refundLib = new \Lib\Mall\Pay();
                $retRefund = $refundLib->refundMoney($refundMoney);
            }

            if($retRefund != false) {
                //在这里打钱好呢？
                if ($resMall['o_status'] == 16) { //走申请退货退款单时的流程
                    $xtype = 36; //要更新到的状态
                    $recvParams = ['r_confirmtime' => date("Y-m-d H:i:s")];
                    $refundModel->updateStatus($sn, $xtype, $this->uid, '', $recvParams);
                } elseif ($resMall['o_status'] == 14) {
                    $xtype = 24; //要更新到的状态
                }

                $confirmRefund = ['uid' => $this->uid, 'id' => $resMall['o_id'], 'xtype' => $xtype];
                $resConfirmRefund = $this->orderLib->confirmRefund($confirmRefund);

                //$data = ['id' => $resMall['g_id']];
                //$goodsLib->itemPost($data);

                if ($resMall['o_payType'] == 4) {
                    //当订单使用的是钱包支付时，更新退款完成状态100
                    $refundModel->updateStatus($sn, 100, $this->uid, '', ['r_cancletime' => date("Y-m-d H:i:s")]);
                }
            }else {
                throw new ServiceException("同意退款失败，请联系系统管理员.");
            }

            $refundModel->commit();
        } catch (\Exception $e) {
            $refundModel->rollback();
            throw $e;
        }

        $this->responseJSON($resConfirmRefund);
    }

    /**
     * 卖家确认退货
     */
    public function confirmReturnGoods() {
        $params = app()->request()->params();
        $sn = $params['o_sn'];
        $resMall = $this->orderLib->detail(['sn' => $sn, 'salesUid' => $this->uid]);

        if ($resMall['o_status'] != 25) {
            throw new ParamsInvalidException("该订单退状态不允许操作." . $resMall['o_status']);
        }

        $xtype = 35; //要更新到的状态
        $refundModel = new \Model\Mall\Refund();
        $goodsLib = new \Lib\Mall\Goods();
        $refundModel->beginTransaction();
        try {

            //在这里打钱好呢？

            $recvParams = ['r_confirmtime' => date("Y-m-d H:i:s")];
            $refundModel->updateStatus($sn, $xtype, $this->uid, '', $recvParams);   //修改退货状态

            $confirmRefund = [
                'uid' => $this->uid,
                'id' => $resMall['o_id'],
                'xtype' => $xtype,
            ];
            $resConfirmRefund = $this->orderLib->confirmRefund($confirmRefund);

            /* $stock = current($goodsInfo['list'])['g_stock'] ? current($goodsInfo['list'])['g_stock'] : 0;
              $data = array(
              'id' => $resMall['g_id'],
              'stock' => $stock+1,
              );
              $goodsLib->itemPost($data);  //修改商品库存 */

            //在这里打钱好呢？还是在这里打钱好呢？
            $refundMoney['o_sn'] = $sn;
            $refundLib = new \Lib\Mall\Pay();
            $retRefund = $refundLib->refundMoney($refundMoney);

            if ($resMall['o_payType'] == 4) {
                //当订单使用的是钱包支付时，更新退款完成状态为100
                $refundModel->updateStatus($sn, 100, $this->uid, '', ['r_cancletime' => date("Y-m-d H:i:s")]);
            }

            $refundModel->commit();
        } catch (\Exception $e) {
            $refundModel->rollback();
            throw $e;
        }

        $this->responseJSON($resConfirmRefund);
    }

    /**
     * 卖家同意退货
     */
    public function argeeRefundGoods() {

        $params = app()->request()->params();
        $sn = $params['o_sn'];
        $resMall = $this->orderLib->detail(['sn' => $sn, 'salesUid' => $this->uid]);
        //后台管理员操作时，可以将卖家拒绝退货的订单，状态修改为同意退货
        if ($resMall['o_status'] != 15 && $resMall['o_status'] !=45) {
            throw new ParamsInvalidException("该订单退状态不允许操作." . $resMall['o_status']);
        }

        $xtype = 25; //要更新到的状态
        $recvParams = [
            'recv_address' => $params['recv_address'],
            'recv_province' => $params['recv_province'],
            'recv_city' => $params['recv_city'],
            'recv_area' => $params['recv_area'],
            'recv_linkman' => $params['recv_linkman'],
            'recv_tel' => $params['recv_tel'],
            'r_agreetime' => date("Y-m-d H:i:s"),
            'r_utime' => date("Y-m-d H:i:s"),
        ];

        $refundModel = new \Model\Mall\Refund();
        $refundModel->beginTransaction();
        try {
            $resRefund = $refundModel->updateStatus($sn, $xtype, $this->uid, '', $recvParams);

            $confirmRefund = [
                'uid' => $this->uid,
                'id' => $resMall['o_id'],
                'xtype' => $xtype,
            ];

            $resConfirmRefund = $this->orderLib->confirmRefund($confirmRefund);
            $refundModel->commit();
        } catch (\Exception $e) {
            $refundModel->rollback();
            throw $e;
        }

        $this->responseJSON($resConfirmRefund);
    }

    /**
     * 卖家拒绝退货
     */
    public function refuseRefundGoods() {

        $params = app()->request()->params();
        $sn = $params['o_sn'];

        $resMall = $this->orderLib->detail(['sn' => $sn, 'salesUid' => $this->uid]);
        if ($resMall['o_status'] != 15) {
            throw new ParamsInvalidException("该订单退状态不允许操作." . $resMall['o_status']);
        }

        $xtype = 45; //要更新到的状态
        $recvParams = [
            'r_agreetime' => date("Y-m-d H:i:s"),
            'r_utime' => date("Y-m-d H:i:s"),
        ];

        $refundModel = new \Model\Mall\Refund();
        $refundModel->beginTransaction();
        try {
            $resRefund = $refundModel->updateStatus($sn, $xtype, $this->uid, '', $recvParams);

            $confirmRefund = [
                'uid' => $this->uid,
                'id' => $resMall['o_id'],
                'xtype' => $xtype,
            ];

            $resConfirmRefund = $this->orderLib->confirmRefund($confirmRefund);
            $refundModel->commit();
        } catch (\Exception $e) {
            $refundModel->rollback();
            throw $e;
        }

        $this->responseJSON($resConfirmRefund);
    }

    /**
     * 删除订单
     */
    public function deleteOrder() {

        $params = app()->request()->params();
        $sn = $params['o_sn'];
        $type = $params['type'];
        if (!in_array($type, [1, 2]) || empty($sn)) {
            throw new ParamsInvalidException("参数传递错误.");
        }
        $map['sn'] = $sn;
        if ($type == 1) {  //买家删除订单
            $map['uid'] = $this->uid;
        } else {  //卖家删除订单
            $map['salesUid'] = $this->uid;
        }

        $resMall = $this->orderLib->detail($map);
        if ($type == 1) {
            if (!in_array($resMall['o_status'], [11, 24, 35, 36,100]) || (in_array($resMall['del_status'], [81, 80]))) {
                throw new ParamsInvalidException("该订单退状态不允许操作." . $resMall['o_status']);
            }

            if ($resMall['del_status'] == 82) {
                $del_status = 80;
            } else {
                $del_status = 81; //要更新到的状态
            }
        } else {
            if (!in_array($resMall['o_status'], [11, 24, 35, 36,100]) || (in_array($resMall['del_status'], [82, 80]))) {
                throw new ParamsInvalidException("该订单退状态不允许操作." . $resMall['o_status']);
            }

            if ($resMall['del_status'] == 81) {
                $del_status = 80;
            } else {
                $del_status = 82; //要更新到的状态
            }
        }

        $confirmRefund = [
            'uid' => $this->uid,
            'id' => $resMall['o_id'],
            'del_status' => $del_status,
            'type' => $type
        ];
        $resConfirmRefund = $this->orderLib->delete($confirmRefund);

        $this->responseJSON($resConfirmRefund);
    }

    /* public function deleteOrder()
      {

      $params = app()->request()->params();
      $sn = $params['o_sn'];
      $type = $params['type'];
      if (!in_array($type, [1, 2]) || empty($sn)) {
      throw new ParamsInvalidException("参数传递错误.");
      }
      $map['sn'] = $sn;
      if ($type == 1){  //买家删除订单
      $map['uid'] = $this->uid;
      }else{  //卖家删除订单
      $map['salesUid'] = $this->uid;
      }

      $resMall = $this->orderLib->detail($map);
      if ($type == 1){
      if (!in_array($resMall['o_status'], [11, 24, 35, 36])) {
      throw new ParamsInvalidException("该订单退状态不允许操作.".$resMall['o_status']);
      }
      }else {
      if (!in_array($resMall['o_status'], [11, 24, 35, 36])) {
      throw new ParamsInvalidException("该订单退状态不允许操作.".$resMall['o_status']);
      }
      }

      $recvParams = [
      'r_agreetime' => date("Y-m-d H:i:s"),
      'r_utime' => date("Y-m-d H:i:s"),
      ];
      $refundModel = new \Model\Mall\Refund();
      $refundModel->beginTransaction();
      try {
      if ($type == 1){
      if ($resMall['o_status'] == 82) {
      $xtype = 80;
      }else {
      $xtype = 81; //要更新到的状态
      }

      $resRefund = $refundModel->updateStatus($sn, $xtype, '', $this->uid, $recvParams);
      }else {
      if ($resMall['o_status'] == 81) {
      $xtype = 80;
      }else {
      $xtype = 82; //要更新到的状态
      }
      $resRefund = $refundModel->updateStatus($sn, $xtype, $this->uid, '', $recvParams);
      }

      $confirmRefund = [
      'uid' => $this->uid,
      'id' => $resMall['o_id'],
      'xtype' => $xtype,
      'type' => $type
      ];
      $resConfirmRefund = $this->orderLib->delete($confirmRefund);
      $refundModel->commit();

      } catch (\Exception $e) {
      $refundModel->rollback();
      throw $e;

      }

      $this->responseJSON($resConfirmRefund);
      } */

    /**
     * 订单详情
     */
    public function detail() {
        $params = app()->request()->params();
        $act = app()->request()->params('act');


        if (!isset($params['sn']) || !isset($params['type'])) {
            throw new ParamsInvalidException("缺少参数,请检查.");
        }

        if (!in_array($params['type'], [1, 2])) {
            throw new ParamsInvalidException('type 订单类型不正确');
        }

        if ($params['type'] == 1) {//买家查看订单详情
            $params['uid'] = $this->uid;
        } elseif ($params['type'] == 2) {//卖家查看订单详情
            $params['salesUid'] = $this->uid;
        }
        $type = $params['type'];

        unset($params['type']);

        /* if(isset($act) && ($act='new')){
          $result = $this->orderLib->detail($params);
          if ($result) {
          foreach ($result as $resMall) {
          $resMalls[] = $this->getGoods($resMall, $params['sn']);
          }
          }
          }else { */
        $resMall = $this->orderLib->detail($params);
        if ($resMall) {
            if(is_array($resMall)){
                if(!empty($resMall['o_createDate'])) {
                    if ($resMall['g_type'] == 4) {
                        $resMall['a_payRemainingSeconds'] = strtotime("{$resMall['o_createDate']}+3 day") - time();
                    } else {
                        $resMall['a_payRemainingSeconds'] = strtotime("{$resMall['o_createDate']}+30 minute") - time();
                    }
                }

                if (!empty($resMall['o_id'])){
                    $resMalls = $this->getGoods($resMall, $params['sn'], $type);
                }else {
                    foreach ($resMall as $val){
                        $resMalls[] = $this->getGoods($val, $params['sn'], $type);
                    }
                }
            }
        }
        //}

        $this->responseJSON($resMalls);
    }

    private function getGoods($resMall, $sn, $type=1) {
        if($resMall['g_type'] == 4 ||$resMall['g_type'] == 7 ) { //拍品信息/定制信息

        }else {
            $goodsInfo = $this->goodsLib->detailGet(['id' => $resMall['g_id']]);

            $resMall['g_madeTime'] = $goodsInfo['item'][0]['g_madeTime'];
            $resMall['g_width'] = $goodsInfo['item'][0]['g_width'];
            $resMall['g_high'] = $goodsInfo['item'][0]['g_high'];
            $resMall['g_material'] = $goodsInfo['item'][0]['g_material'];
            $resMall['categoryName'] = $goodsInfo['item'][0]['categoryName'];
        }

        $resMall['o_provinceName'] = \Lib\Common\Region::getRegionNameByCode($resMall['o_provinceCode']);
        $resMall['o_cityName'] = \Lib\Common\Region::getRegionNameByCode($resMall['o_cityCode']);
        $resMall['o_areaName'] = \Lib\Common\Region::getRegionNameByCode($resMall['o_areaCode']);

        $userLib = new \Lib\User\User();
        $userIm = $userLib->queryUserIm($resMall['u_id']);
        if (!$userIm['ue_imId']){
            $user_data = $userLib->getUserInfo([$resMall['u_id']]);
            if (current($user_data)['u_phone'] == '12300000000'){
                $imId = config('app.kefu_imId');
            }
        }else {
            $imId = $userIm['ue_imId'];
        }
        $resMall['ue_imId'] = $imId;

        if($resMall['g_type'] == 7 && empty($resMall['o_salesUid'])) {
            //定制订单存在卖家暂未产生的情况
            $resMall['sales_imId'] = '';
            $resMall['certification'] = 0;
        }else {
            $salesIm = $userLib->queryUserIm($resMall['o_salesUid']);
            $resMall['sales_imId'] = $salesIm['ue_imId'];

            $os[] = $resMall;
            if ($type == 1) {
                $uid = 'o_salesUid';
            } else {
                $uid = 'u_id';
            }
            $userLib->extendUserInfos2Array($os, $uid, array(
                'u_nickname' => 'saler_nickname',
                'u_realname' => 'saler_realname',
                'u_avatar' => 'saler_avatar',
            ));

            unset($resMall);
            $resMall = $os[0];
            $resMall['certification'] = $this->certificationModel->getType($resMall['o_salesUid']);
        }

        $refundModel = new \Model\Mall\Refund();
        $retInfo = $refundModel->getRefund($sn, $this->uid);
        $resMall['r_content'] = $retInfo['r_content'];
        if ($retInfo['r_images']) {
            $image_list = json_decode($retInfo['r_images']);
            $resMall['r_images'] = [];
            foreach ($image_list as $value) {
                $resMall['r_images'][] = FileHelper::getFileUrl($value->r_img, 'mall_refund_images');
            }
        }

        $resMall = $this->orderStatus($resMall);
        return $resMall;
    }

    /**
     * 买家申请退货上传退货图片
     */
    public function uploadRefundImages() {
        $types = [
            'image/jpeg' => "jpg",
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        $size = 10 * 1024 * 1024;
        $filesData = FileHelper::uploadFiles('mall_refund_images', $size, $types);
        if ($filesData) {
            if (empty($filesData['result'])) {
                $this->responseJSON(empty($filesData['data']) ? [] : $filesData['data'], 1, 1,
                    empty($filesData['message']) ? '' : $filesData['message']);
            } else {
                $this->responseJSON($filesData['data']);
            }
        } else {
            $this->responseJSON([], 1, 1, '上传文件时发生异常');
        }
    }

    private function orderStatus($resMall) {
        $resMall['o_flag'] = '';
        $resMall['o_tip'] = '';
        if ($resMall['o_status'] == 0) {//下单后，半小时内无支付自动关闭
            $etime = strtotime($resMall['o_createDate']) + 1800;
            if (time() < $etime) {
                $leaveTime = timediff(time(), $etime);
            } else {
                $leaveTime = ['day' => 0, 'hour' => 0, 'min' => 0, 'sec' => 0];
            }
            $resMall['o_flag'] = '等待买家付款';
            $resMall['o_tip'] = '剩' . $leaveTime['day'] . '天' . $leaveTime['hour'] . '小时' . $leaveTime['min'] . '分钟自动关闭';
        }

        if (in_array($resMall['o_status'], [1])) {
            $resMall['o_flag'] = '买家已付款';
            $resMall['o_tip'] = '请稍等卖家正在发货中';
        }
        if (in_array($resMall['o_status'], [2])) {//发货后,7天自动确认收货
            $etime = strtotime($resMall['o_shippingDate']) + 604800;
            if (time() < $etime) {
                $leaveTime = timediff(time(), $etime);
            } else {
                $leaveTime = ['day' => 0, 'hour' => 0, 'min' => 0, 'sec' => 0];
            }

            $resMall['o_flag'] = '卖家已发货';
            $resMall['o_tip'] = '剩' . $leaveTime['day'] . '天' . $leaveTime['hour'] . '小时' . $leaveTime['min'] . '自动确认';
        }

        if ($resMall['o_status'] == 3) {
            $resMall['o_flag'] = '已收货';
            $resMall['o_tip'] = "交易成功，期待您的再次光顾";
        }
        if ($resMall['o_status'] == 100) {
            $resMall['o_flag'] = '交易成功';
            $resMall['o_tip'] = "交易成功，期待您的再次光顾";
        }
        if ($resMall['o_status'] == 11) {
            $resMall['o_flag'] = '订单已取消';
            //$resMall['o_tip'] = '买家未在规定时间内付款';
            $resMall['o_tip'] = '取消原因：' . $resMall['o_cancelReason'];
        }
        if (in_array($resMall['o_status'], [14, 16])) {
            $resMall['o_flag'] = '退款中';
            $resMall['o_tip'] = '退款原因：' . $resMall['o_cancelReason'];
        }
        if (in_array($resMall['o_status'], [15, 25])) {
            $resMall['o_flag'] = '退货退款中';
            $resMall['o_tip'] = '退货退款原因：' . $resMall['o_cancelReason'];
        }
        if (in_array($resMall['o_status'], [24])) {
            $resMall['o_flag'] = '退款成功，交易关闭';
            $resMall['o_tip'] = '货款已原路返回您的账户';
        }
        if (in_array($resMall['o_status'], [35])) {
            $resMall['o_flag'] = '退货退款成功，交易关闭';
            $resMall['o_tip'] = '货款已原路返回您的账户';
        }
        if (in_array($resMall['o_status'], [36])) {
            $resMall['o_flag'] = '交易关闭';
        }
        if (in_array($resMall['o_status'], [45])) {
            $resMall['o_flag'] = '拒绝退货退款';
            $resMall['o_tip'] = '商家已拒绝，如有疑问请联系掌玩客服';
        }

        return $resMall;
    }

    public function cliClose() {
        $params = app()->request()->params();
        $this->orderLib->cliClose($params);
    }

    public function replace(){
        $params = app()->request()->params();
        $res = $this->orderLib->replace($params);

        $this->responseJSON($res);
    }

    /**
     * 他的订单
     */
    public function othersLists() {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        $params['listType'] = app()->request()->params('listType', 0);   //1=买家  0=卖家
        $params['buyer'] = app()->request()->params('uid');  //买家的uid
        $resMall = $this->orderLib->lists($params);

        if ($params['listType'] == 1){
            $uid = 'o_salesUid';
        }else {
            $uid = 'u_id';
        }
        $userLib = new \Lib\User\User();
        $userLib->extendUserInfos2Array($resMall, $uid, array(
            'u_nickname' => 'o_nickname',
            'u_realname' => 'o_realname',
            'u_avatar' => 'o_avatar',
            'ue_imId' => 'ue_imId',
            'ue_imPassword' => 'ue_imPassword',
        ));

        foreach ($resMall as $key => &$val) {
            if (empty($val['o_realname'])){
                $val['o_realname'] = $val['o_nickname'];
            }

            $val['o_provinceName'] = \Lib\Common\Region::getRegionNameByCode($val['o_provinceCode']);
            $val['o_cityName'] = \Lib\Common\Region::getRegionNameByCode($val['o_cityCode']);
            $val['o_areaName'] = \Lib\Common\Region::getRegionNameByCode($val['o_areaCode']);

            if ($params['listType'] == 1){
                $val['certification'] = $this->certificationModel->getType($val['o_salesUid']);
            }else {
                $val['certification'] = $this->certificationModel->getType($val['u_id']);
            }

            if($val['g_type'] == 4 || $val['g_type'] == 7) {
                $orderStatus = $this->orderStatus($val);
                $val['o_flag'] = $orderStatus['o_flag'];
                $val['sales_imId'] = $val['ue_imId'];
                $val['sales_imPassword'] = $val['ue_imPassword'];
            }else {
                //检测商品是否存在
                $rel = Goods::detailGet($val['g_id']);
                if ($rel) {
                    $goodsInfo = $this->goodsLib->detailGet(['id' => $val['g_id']]);

                    $val['g_width'] = $goodsInfo['item'][0]['g_width'];
                    $val['g_high'] = $goodsInfo['item'][0]['g_high'];
                    $val['g_material'] = $goodsInfo['item'][0]['g_material'];
                    $val['g_madeTime'] = $goodsInfo['item'][0]['g_madeTime'];
                    $val['categoryName'] = $goodsInfo['item'][0]['categoryName'];
                    $val['g_surfaceImg'] = $goodsInfo['item'][0]['g_surfaceImg'];

                    $orderStatus = $this->orderStatus($val);
                    $val['o_flag'] = $orderStatus['o_flag'];

                    $val['sales_imId'] = $val['ue_imId'];
                    $val['sales_imPassword'] = $val['ue_imPassword'];
                } else {
                    unset($resMall[$key]);
                }
            }
        }

        $userLib->extendUserInfos2Array($resMall, 'u_id', array(
            'ue_imId' => 'ue_imId',
            'ue_imPassword' => 'ue_imPassword',
        ));

        $this->responseJSON($resMall);
    }

    /**
     * 保存订单收货信息
     */
    public function saveAddress()
    {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        $res = $this->orderLib->orderSaveAddress($params);

        $this->responseJSON($res);
    }

    /**
     * 新增订单工单
     * @throws ParamsInvalidException
     */
    public function addWorkOrder()
    {
        $params = app()->request()->params();
        $osn = isset($params['osn']) ? $params['osn'] : '';
        if (empty($osn)) {
            throw new ParamsInvalidException("参数不正确");
        }
        $orderData = OrderManager::getOrderBySn($osn);
        if (empty($orderData)) {
            throw new ParamsInvalidException("订单不存在");
        }
        if ($this->uid == $orderData['u_id']) {
            //买家
            $creatorType = 1;
        } elseif ($this->uid == $orderData['o_salesUid']) {
            //卖家
            $creatorType = 2;
        } else {
            throw new ParamsInvalidException("你不能发起此订单的工单");
        }
        //校验此订单是否有待处理、处理中的工单
        $workOrderData = WorkOrderManager::getList(['osn' => $osn, 'userId' => $this->uid, 'status' => [0, 1]]);
        if ($workOrderData) {
            throw new ParamsInvalidException("此订单有未处理完成的工单，不能重复申请");
        }
        WorkOrderManager::addWorkOrder($osn, $creatorType, $this->uid);
        $this->responseJSON(true);
    }

    /**
     * 获取订单自提地址
     */
    public function getSelfPickupAddress()
    {
        $address = '';
        $orderSelfPickup = conf('config.orderSelfPickup');
        if ($orderSelfPickup) {
            $provinceName = Region::getRegionNameByCode($orderSelfPickup['provinceCode']);
            $cityName = Region::getRegionNameByCode($orderSelfPickup['cityCode']);
            $areaName = Region::getRegionNameByCode($orderSelfPickup['areaCode']);
            $address = $provinceName . $cityName . $areaName . $orderSelfPickup['address'];
        }

        $this->responseJSON($address);
    }
}
