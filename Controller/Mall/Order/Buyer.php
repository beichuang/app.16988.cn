<?php

/**
 * 订单
 * @author Administrator
 *
 */

namespace Controller\Mall\Order;

use Cli\Worker\AuctionPledge;
use Exception\ServiceException;
use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Rest\Mall\PledgeManager;

class Buyer extends BaseController {

    private $goodsLib = null;
    private $orderLib = null;

    public function __construct() {
        parent::__construct();
        $this->goodsLib = new \Lib\Mall\Goods();
        $this->orderLib = new \Lib\Mall\Order();
    }

    public function test() {
        $AuctionPledgeModel = new AuctionPledge();
        $AuctionPledgeModel->run();
    }

    /**
     * 新增订单，钱包充值
     */
    public function recharge() {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        $params['g_name'] = '钱包充值';
        //'订单类型：1:商品,2:充值,3:打赏,4:拍卖,5:秒杀,6:砍价,7:定制,8:保证金',
        $params['g_type'] = 2;
        $resMall = $this->orderLib->recharge($params);
        $orderId = $resMall;
        $this->responseJSON(array(
            'order_id' => $orderId
        ));
    }

    /**
     * 新增订单，保证金充值
     */
    public function pledgeRecharge()
    {
        $g_id = app()->request()->params('g_id');
        if (!$g_id) {
            throw new \Exception\ParamsInvalidException("拍品id必须!");
        }
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
//        $params['uid'] = '949081942';
        $params['g_id'] = $g_id;

        //'订单类型：1:商品,2:充值,3:打赏,4:拍卖,5:秒杀,6:砍价,7:定制,8:保证金',
        $params['g_type'] = 8;
        $params['g_name'] = '充值保证金';

        $resMall = $this->orderLib->recharge($params);
        $orderId = $resMall;
        $this->responseJSON(array(
            'order_id' => $orderId
        ));
    }

    /**
     * 新增订单，打赏
     */
    public function gratuity() {
        $params = app()->request()->params();

        //打赏者id
        $params['uid'] = $this->uid;
        $resMall = $this->orderLib->gratuity($params);
        $orderId = $resMall;

        $wallet = new \Model\Pay\Wallet();
        $walletInfo = $wallet->getWallet($this->uid);
        $this->responseJSON(array(
            'order_id' => $orderId,
            'w_balance' => $walletInfo['w_balance']
        ));
    }

    public function gratuityMsg() {
        $sn = app()->request()->params('id');
        if (!$sn) {
            throw new ParamsInvalidException("数据错误");
        }

        $order = new \Lib\Mall\Order();
        $ret = $order->detail(['sn' => $sn]);

        $queueAppid = config('app.queue_common_params.appid');
        $params = array(
            'id' => $ret['g_id'],
            'type' => 70,
            'desc' => '宝贝打赏',
            'app_id' => '30000',
            'o_sn' => $sn,
            'uid' => $this->uid,
        );
        $order->sendmsg($params);
        $this->responseJSON(true);
    }

    /**
     * 新增订单，下单
     */
    public function add() {
        $params = app()->request()->params();
        $uid = $this->uid;
        $params['uid'] = $uid;
        $uCartModel = new \Model\Mall\UserCart();
        //多商户多商品购买
        if (isset($params['carts']) && !empty($params['carts'])) {
            $carts = json_decode($params['carts'], true);
            $totalPrice = 0;
            $cartData = array();
            $card_ids = array();
            foreach ($carts as &$cart) {
                $oneShop_cart = explode('|', $cart['c_id']);
                foreach ($oneShop_cart as $oneCart) {
                    $res = $uCartModel->checkCart($uid, $oneCart);
                    if (empty($res)) {
                        throw new ParamsInvalidException("购物车数据出错");
                    }

                    $res['guestContent'] = isset($cart['guestContent'])?$cart['guestContent']:'';
                    $cartData[] = $res;
                    $totalPrice += ($res['ucart_goodsPrice'] * 100 * $res['ucart_goodsNum']) / 100;
                    $card_ids[] = $oneCart;
                }
            }

            $params['carts'] = json_encode($cartData);
            $params['totalPrice'] = $totalPrice;

            $resMall = $this->orderLib->addmore($params);
        } else {  //单商品购买
            $params['count'] = app()->request()->params('count', 1);
            $resMall = $this->orderLib->add($params);
        }

        $orderId = $resMall;

        //删除购物车中商品
        if ($orderId) {
            if ($card_ids) {
                foreach ($card_ids as $cart) {
                    $data['c_id'] = $cart;
                    $uCartModel->deleteByCid($data);
                }
            } else {
                $data['u_id'] = $this->uid;
                $data['g_id'] = $params['gid'];
                $uCartModel->deleteByOrder($data);
            }
        }

        $this->responseJSON(array('order_id' => $orderId));
    }

    /**
     * 订单确认收货
     */
    public function finish() {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        if (!isset($params['osn'])) {
            throw new ParamsInvalidException("订单号必须");
        }

        $resMall = $this->orderLib->finish($params);
        $this->responseJSON($resMall);
    }

    /**
     * 评论订单
     * @throws ParamsInvalidException
     * @throws ServiceException
     */
    public function commentOrder()
    {
        $orderId=intval(app()->request()->params('o_id'));
        $oc_expressPackScore=intval(app()->request()->params('oc_expressPackScore'));
        $oc_expressSpeedScore=intval(app()->request()->params('oc_expressSpeedScore'));
        $oc_expressAttitudeScore=intval(app()->request()->params('oc_expressAttitudeScore'));
        if(!$orderId){
            throw new ParamsInvalidException("订单id必须");
        }
        if(
            $oc_expressAttitudeScore<0 ||$oc_expressPackScore<0 ||$oc_expressSpeedScore<0
            ||$oc_expressAttitudeScore>5 ||$oc_expressPackScore>5 ||$oc_expressSpeedScore>5
        ){
            throw new ParamsInvalidException("评分只能是1~5");
        }

        if(!$orderInfo=app('mysqlbxd_mall_user')->fetch('select `o_status` from `order` where o_id='.$orderId)){
            throw new ParamsInvalidException('订单不存在');
        }
        if(!in_array($orderInfo['o_status'],[3,100])){
            throw new ParamsInvalidException('订单当前不能评价');
        }
        if($orderComment=app('mysqlbxd_mall_user')->fetch('select o_id from `order_comment` where o_id='.$orderId)){
            throw new ParamsInvalidException('订单已评论');
        }
        $data=[
            'o_id'=>$orderId,
            'oc_expressPackScore'=>$oc_expressPackScore,
            'oc_expressSpeedScore'=>$oc_expressSpeedScore,
            'oc_expressAttitudeScore'=>$oc_expressAttitudeScore,
            'oc_time'=>date('Y-m-d H:i:s'),
        ];
        list($count,)=app('mysqlbxd_mall_user')->insert('order_comment',$data);
        if(!$count){
            throw new ServiceException('保存数据错误');
        };
        //订单评论加积分
        (new \Lib\User\UserIntegral())->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_ORDER_COMMENT_ADD);
        $this->responseJSON(1);
    }

    /**
     * 评论订单商品
     */
    public function commentGoods()
    {
        $orderId=intval(app()->request()->params('o_id'));
        $goodsId=intval(app()->request()->params('g_id'));
        $score=app()->request()->params('gc_score');
        $gc_content=app()->request()->params('gc_content');
        if(!$orderId){
            throw new ParamsInvalidException("订单id必须");
        }
        if(!$goodsId){
            throw new ParamsInvalidException("商品id必须");
        }
        if($score<0 ||$score>5){
            throw new ParamsInvalidException("评分只能是1~5");
        }
        if(strlen($gc_content)<3){
            throw new ParamsInvalidException("内容过少");
        }
        if ($check_content = filter_words(cutstr_html($gc_content))) {
            throw new ParamsInvalidException("评论内容包含敏感词");
        }
        if(!$orderInfo=app('mysqlbxd_mall_user')->fetch("select * from `order` where o_id={$orderId} and u_id={$this->uid}")){
            throw new ParamsInvalidException('订单不存在');
        }
        if(!in_array($orderInfo['o_status'],[3,100])){
            throw new ParamsInvalidException('订单当前不能评价');
        }
        if (in_array($orderInfo['g_type'], [4, 7])) {
            if ($goodsId != $orderInfo['g_id']) {
                throw new ParamsInvalidException('没有购买此商品');
            }
        } else {
            if (!$goods = app('mysqlbxd_mall_user')->fetch("select g_id from `cart` where o_id={$orderId} and g_id={$goodsId}")) {
                throw new ParamsInvalidException('没有购买此商品');
            }
        }
        if($goodsComment=app('mysqlbxd_app')->fetch("select gc_id from `goods_comment` where o_id={$orderId} and g_id={$goodsId}")){
            throw new ParamsInvalidException('已评论');
        }
        $data=[
            'gc_pid'=>0,
            'u_id'=>$this->uid,
            'g_id'=>$goodsId,
            'gc_content'=>$gc_content,
            'gc_title'=>'',
            'gc_time'=>date('Y-m-d H:i:s'),
            'gc_likeTimes'=>0,
            'o_id'=>$orderId,
            'gcf_id'=>0,
            'gc_score'=>$score,
        ];
        list($count,)=app('mysqlbxd_app')->insert('goods_comment',$data);
        if(!$count){
            throw new ServiceException('保存数据错误');
        };
        //商品评论加积分
        (new \Lib\User\UserIntegral())->addIntegral($this->uid,\Lib\User\UserIntegral::ACTIVITY_GOODS_COMMENT_ADD);
        $this->responseJSON(1);
    }
}
