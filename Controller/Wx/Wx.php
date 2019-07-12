<?php

namespace Controller\Wx;

use Exception\ParamsInvalidException;
use Exception\ServiceException;
use Framework\Helper\WxHelper;
use Lib\Base\BaseController;
use Lib\WxMini\WXLoginHelper;
use Model\User\Distribution;
use Rest\Mall\Facade\DistributionManager;
use Rest\User\Facade\UserDVipInviteManager;
use Rest\User\Facade\UserManager;

/**
 * 微信开发
 *
 * @author yangzongxun
 *
 */
class Wx extends BaseController
{
    /**
     * 购物车页面
     */
    public function myCartPage()
    {
        $openId = WxHelper::getOpenId();

        $host=app()->request()->getHost();
        if (!empty($openId)) {
            $redirect_uri = 'https://'.$host.'/html/zwHomeH5.html#/cartListe';
            app()->redirect($redirect_uri);
        }
    }
    /**
     * 我的订单页面
     */
    public function myOrderPage()
    {
        $openId = WxHelper::getOpenId();

        $host=app()->request()->getHost();
        if (!empty($openId)) {
            $redirect_uri = 'https://'.$host.'/html/apph5/myOrder.html#/allOrder';
            app()->redirect($redirect_uri);
        }
    }

    /**
     * 公众号首页
     */
    public function indexPage()
    {
        $openId = WxHelper::getOpenId();
        if (!empty($openId)) {
            $host = app()->request()->getHost();
            $redirect_uri = 'https://' . $host . '/html/zwHomeH5.html';
            app()->redirect($redirect_uri);
        }
    }

    /**
     * 授权登录
     */
    public function auth()
    {
        $type = app()->request()->params('type');
        $gid = app()->request()->params('id');
        $uid = app()->request()->params('uid');

        if ($type) {
            $query = '';
            if (isset($type) && $type) {
                $query .= 'type=' . $type;
            }
            if (isset($gid) && $gid) {
                $query .= '&id=' . $gid;
            }
            if (isset($uid) && $uid) {
                $query .= '&uid=' . $uid;
            }
            $host=app()->request()->getHost();
            $redirect_uri = 'https://'.$host.'/wx/wx/getOpenId?' . $query;
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . config('app.weChat.appid')
                . '&redirect_uri=' . urlencode($redirect_uri) . '&response_type=code&scope=snsapi_base#wechat_redirect';
        } else {
            $redirect_uri = app()->request()->params('url');
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . config('app.weChat.appid')
                . '&redirect_uri=' . urlencode($redirect_uri) . '&response_type=code&scope=snsapi_base&state=' . $uid . '#wechat_redirect';
        }

        app()->redirect($url);
    }

    public function authNew()
    {
        $type = app()->request()->params('type');
        $g_id = app()->request()->params('g_id');
        $ugc_id = app()->request()->params('ugc_id');

        if ($type) {
            $query = '';
            if (isset($type) && $type) {
                $query .= 'type=' . $type;
            }
            if (isset($g_id) && $g_id) {
                $query .= '&g_id=' . $g_id;
            }
            if (isset($ugc_id) && $ugc_id) {
                $query .= '&ugc_id=' . $ugc_id;
            }

            $redirect_uri = 'https://' . config('app.baseDomain') . '/wx/wx/getOpenIdInsertUserInfo?' . $query;
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . config('app.weChat.appid')
                . '&redirect_uri=' . urlencode($redirect_uri) . '&response_type=code&scope=snsapi_userinfo#wechat_redirect';
        } else {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        app()->redirect($url);
    }

    public function getOpenIdInsertUserInfo()
    {
        $code = app()->request()->params('code');
        $gid = app()->request()->params('g_id');
        $ugc_id = app()->request()->params('ugc_id');
        $type = app()->request()->params('type');
        $baseDomain = config('app.baseDomain');
        if (!$code) {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        $res = $this->access_token($code);
        $openId = $res['openid'];
        $access_token = $res['access_token'];
        $ret = $this->_check($openId, $access_token);
        if ($ret === false) {
            throw new \Exception\ParamsInvalidException("认证失败");
        }
        setcookie('openid', $openId, time() + 3600 * 24, '/');

        if ($type == 'beginCut') {
            $redirect_uri = "https://{$baseDomain}/html/apph5/cutdownPrice.html#/process?g_id={$gid}";
        } elseif ($type == 'helpCut') {
            if (empty($ugc_id) || empty($gid)) {
                throw new \Exception\ParamsInvalidException("缺少参数");
            }
            $redirect_uri = "https://{$baseDomain}/html/apph5/cutdownPrice.html#/processShare?g_id={$gid}&ugc_id={$ugc_id}";
        } elseif ($type == 'listCut') {
            $redirect_uri = "https://{$baseDomain}/html/apph5/cutdownPrice.html#/index";
        } elseif ($type == 'detailCut') {
            $redirect_uri = "https://{$baseDomain}/html/apph5/cutdownPrice.html#/detail?g_id={$gid}";
        }

        //存储用户信息
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $access_token . "&openid=" . $openId . "&lang=zh_CN";

        $this->_wx_api_action = 'GetUserInfo';
        $return = $this->getcurl($url);
        if (isset($return['errcode']) && $return['errcode'] > 0) {
            $access_token = WxHelper::getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $access_token . "&openid=" . $openId . "&lang=zh_CN";
            $this->_wx_api_action = 'GetUserInfo';
            $return = $this->getcurl($url);
        }

        //如果关注了，存入数据库
        $itemOpenidQuery = new \Lib\Mall\Goods();
        if ($return['subscribe'] == 1) {
            //关注后新存
            $addData['uo_subscribe'] = $return['subscribe'];
            $addData['uo_openId'] = $openId;
            $addData['uo_nickname'] = isset($return['nickname']) ? $return['nickname'] : '';
            $addData['uo_sex'] = isset($return['sex']) ? $return['sex'] : '';
            $addData['uo_headimgurl'] = isset($return['headimgurl']) ? $return['headimgurl'] : '';
            $addData['uo_subscribe_time'] = isset($return['subscribe_time']) ? date('Y-m-d H:i:s', $return['subscribe_time']) : '';
        }else{
            //未关注，修改关注信息
            $addData['uo_subscribe'] = $return['subscribe'];
            $addData['uo_openId'] = $openId;
        }

        $itemOpenidQuery->itemOpenidQuery($addData);

        app()->redirect($redirect_uri);
    }

    private function _authNew($type = '', $g_id = '', $ugc_id = '')
    {
        if ($type) {
            $query = '';
            if (isset($type) && $type) {
                $query .= 'type=' . $type;
            }
            if (isset($g_id) && $g_id) {
                $query .= '&g_id=' . $g_id;
            }
            if (isset($ugc_id) && $ugc_id) {
                $query .= '&ugc_id=' . $ugc_id;
            }

            $redirect_uri = 'https://' . config('app.baseDomain') . '/wx/wx/getOpenIdInsertUserInfo?' . $query;
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . config('app.weChat.appid')
                . '&redirect_uri=' . urlencode($redirect_uri) . '&response_type=code&scope=snsapi_userinfo#wechat_redirect';
        } else {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        return ($url);
    }

    /** 通过code换取网页授权access_token
     * @param $code
     * @return mixed
     * @throws \Exception
     */
    private function access_token($code)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . config('app.weChat.appid') . "&secret=" . config('app.weChat.appSecret') . "&code=" . $code . "&grant_type=authorization_code";
        $res1 = file_get_contents($url);
        $res = json_decode($res1, true);
        if ($res && isset($res['access_token']) && $res['access_token']) {
            return $res;
        } else {
            throw new \Exception($res1);
        }
    }

    /** 检验授权凭证（access_token）是否有效
     * @param $openId
     * @param $access_token
     * @return bool
     */
    private function _check($openId, $access_token)
    {
        $url = "https://api.weixin.qq.com/sns/auth?access_token=" . $access_token . "&openid=" . $openId;
        $res1 = file_get_contents($url);
        $res = json_decode($res1, true);
        if ($res['errcode'] === 0) {
            return true;
        } else {
            return false;
        }
    }

    /** 获取openID
     * @return mixed
     * @throws \Exception\ParamsInvalidException
     */
    public function getOpenId()
    {
        $code = app()->request()->params('code');
        $uid = app()->request()->params('state');
        $type = app()->request()->params('type');
        if (!$code) {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        $res = $this->access_token($code);
        $openId = $res['openid'];
        $access_token = $res['access_token'];
        $ret = $this->_check($openId, $access_token);
        if ($ret === false) {
            throw new \Exception\ParamsInvalidException("认证失败");
        }
        setcookie('openid', $openId, time() + 3600 * 24, '/');
        $host=app()->request()->getHost();
        if ($type == 'goods') {
            $uid = app()->request()->params('uid');
            $url = "https://{$host}/html/apph5/myShop.html#/goodsDetail?id=" . app()->request()->params('id');
            if ($uid) {
                $url .= "&uid={$uid}";
            }
        } else if ($type == 'order') {
            $url = "https://{$host}/html/apph5/myOrder.html#/";
        }else if ($type == 'hkzg') {
            $url = "https://{$host}/html/votes.html?openid=".$openId;
        }else if($type == 'zeroactivity'){
            $url = "https://{$host}/html/exp_act.html?openid=".$openId;
        } else {
            $url = "https://{$host}/html/apph5/myShop.html#/?uid=" . $uid;
        }
        app()->redirect($url);
        // $this->responseJSON($openId);
    }

    public function getOpenIdNew()
    {
        $code = app()->request()->params('code');
        $uid = app()->request()->params('state');
        $type = app()->request()->params('type');
        if (!$code) {
            throw new \Exception\ParamsInvalidException("参数错误");
        }

        $res = $this->access_token($code);
        $openId = $res['openid'];
        $access_token = $res['access_token'];
        $ret = $this->_check($openId, $access_token);
        if ($ret === false) {
            throw new \Exception\ParamsInvalidException("认证失败");
        }
        setcookie('openid', $openId, time() + 3600 * 24, '/');

        $host=app()->request()->getHost();
        if ($type == 'goods') {
            $uid = app()->request()->params('uid');
            if ($uid) {
                $url = "https://{$host}/html/apph5/myShop.html#/goodsDetail?uid=" . $uid . "&id=" . app()->request()->params('id');
            } else {
                $url = "https://{$host}/html/apph5/myShop.html#/goodsDetail?id=" . app()->request()->params('id');
            }
        } else if ($type == 'order') {
            $url = "https://{$host}/html/apph5/myOrder.html#/";
        } else {
            $url = "https://{$host}/html/apph5/myShop.html#/?uid=" . $uid;
        }

        app()->redirect($url);
        // $this->responseJSON($openId);
    }

    /**
     * 下单
     */
    public function add()
    {
        $params = app()->request()->params();
        $payChannel=app()->request()->params('payChannel');
        $payChannel=empty($payChannel)?6:$payChannel;
        $openid    = WXLoginHelper::getWxSession(WXLoginHelper::OPENID);
        if($payChannel==12){
            $openid    = is_string($openid)?$openid:$openid[1]['openid'];
        }
        if(!$openid){
            $openid = isset($_COOKIE['openid']) ? $_COOKIE['openid'] : app()->request()->params('openid');
        }
        $params['buyer_uid']=empty($this->uid)?$params['buyer_uid']:$this->uid;
        if (isset($params['orderDistributionType']) && $params['orderDistributionType'] == 1) {
            //分销订单，获取分销类型（商品分销或者拉新，分享人id）
            list($d_uid, $d_type) = $this->getDistributionData($params);
        } else {
            $d_uid = '';
            $d_type = 0;
        }
        $params['d_uid'] = $d_uid;
        $params['d_type'] = $d_type;

        //下单
        $orderLib = new \Lib\Mall\Order();
        if (empty($params['buyer_uid']) || empty($openid) || (empty($params['aid']) && empty($params['isSelfPickup'])) || empty($params['gid'])) {
            throw new \Exception\ParamsInvalidException("参数不全");
        }

        $params['uid'] = $params['buyer_uid'];
        $params['count'] = app()->request()->params('count', 1);
        $resMall = $orderLib->add($params);
        $orderId = $resMall;
        if ($orderId) {
            //  pay/get
            $orderInfo = $orderLib->detail(['sn' => $orderId]);
            if ($orderInfo && $orderInfo['o_pay']>0) {
                $payLib = new \Lib\Mall\Pay();
                $pay_get['tradeId'] = $orderId;
                $pay_get['subject'] = $orderInfo['g_name'];
                $pay_get['totalAmount'] = $orderInfo['o_pay'] * 100;
                $pay_get['timeout'] = 10;
                $pay_get['from'] = 1;
                $resPay = $payLib->payGet($pay_get);
                if ($resPay) {
                    //  pay/init
                    $pay_init['tradeId'] = $orderId;
                    $pay_init['payChannel'] = $payChannel;
                    $pay_init['openId'] = $openid;

                    $trade = new \Model\Pay\Trade();
                    $config = load_row_configs_trim_prefix('api.Mall');
                    $tradeId = $config['appId'] . '-' . $pay_init['tradeId'];
                    $tradeInfo = $trade->getCartTrade($tradeId);
                    if (!$tradeInfo) {
                        $trade_sn = $trade->getTrade($tradeId);
                        if (!$trade_sn) {
                            throw new ParamsInvalidException("交易信息不存在");
                        }
                        $tradeInfo[] = $trade_sn;
                    }

                    $tr_amount = 0;
                    foreach ($tradeInfo as $val) {
                        if ($val['tr_status'] > 1) {
                            throw new ParamsInvalidException("该支付已完成");
                        }
                        $tr_amount += $val['tr_amount'];
                    }

                    //记录该订单的支付方式
                    $extend['tradeId'] = $tradeId;
                    $extend['payChannel'] = $pay_init['payChannel'];
                    $orderLib->orderExtend($extend);
                    $result = $payLib->payInit($pay_init);
                    if ($result) {
                        $arr = current($result);
                        $this->responseJSON($arr);
                    }else{
                        throw new ServiceException('初始化支付失败');
                    }
                }else{
                    throw new ServiceException('下单失败');
                }
            }else if($orderInfo && $orderInfo['o_pay']==0){
                $this->responseJSON(true);
            }else{
                throw new ServiceException('查询订单失败');
            }
        }else{
            throw new ServiceException('下单失败');
        }
    }

    private function getDistributionData($params)
    {
        $distributionLog = '';
        $d_uid = '';
        $d_type = 1;
        $userExtendData = UserManager::getOneUserExtend($this->uid);
        //非传艺人
        if (!empty($userExtendData) && $userExtendData['u_distribution_type'] != 1) {
            $isBuySpecialDistributionGoods = false;
            //分销类型（1：商品分销；2拉新）
            $d_type = isset($params['d_type']) ? $params['d_type'] : 1;
            if (!empty($params['gid']) && $d_type == 2) {
                $buyGoodsIds = $params['gid'];
                $specialDistributionGoodsIdArray = conf('config.specialDistributionGoods');
                if (in_array($buyGoodsIds, $specialDistributionGoodsIdArray)) {
                    $isBuySpecialDistributionGoods = true;
                }
            }

            if ($isBuySpecialDistributionGoods) {
                $d_type = 2; //拉新传艺人
                //是否绑定了邀请人
                $dVipInviteData = UserDVipInviteManager::getByUid($this->uid);
                if ($dVipInviteData && $dVipInviteData['vi_invite_uid']) {
                    $d_uid = $dVipInviteData['vi_invite_uid'];
                } else {
                    $d_uid = isset($params['uid']) ? $params['uid'] : '';
                }
                //判断是否有有效的拉新订单
                $distribution_log_status = DistributionManager::search_unfinsh_add_new_list($this->uid);
                if($distribution_log_status){
                    $d_type = 1;
                }
            } else {
                $d_uid = isset($params['uid']) ? $params['uid'] : '';
                $d_type = 1;
            }
        }
        return [$d_uid, $d_type];
    }

    /**
     * 支付订单
     * @throws ParamsInvalidException
     * @throws ServiceException
     * @throws \Exception\ModelException
     */
    public function pay()
    {
        $o_sn=app()->request()->params('o_sn');
        $openid=WXLoginHelper::getWxSession(WXLoginHelper::OPENID);
        if(!$openid){
            $openid = isset($_COOKIE['openid']) ? $_COOKIE['openid'] : app()->request()->params('openid');
        }
        $orderLib = new \Lib\Mall\Order();
        $payLib = new \Lib\Mall\Pay();
        //  pay/init
        $pay_init['tradeId'] = $o_sn;
        $pay_init['payChannel'] = 6;
        $pay_init['openId'] = $openid;

        $trade = new \Model\Pay\Trade();
        $config = load_row_configs_trim_prefix('api.Mall');
        $tradeId = $config['appId'] . '-' . $pay_init['tradeId'];
        $tradeInfo = $trade->getCartTrade($tradeId);
        if (!$tradeInfo) {
            $trade_sn = $trade->getTrade($tradeId);
            if (!$trade_sn) {
                throw new ParamsInvalidException("交易信息不存在");
            }
            $tradeInfo[] = $trade_sn;
        }

        $tr_amount = 0;
        foreach ($tradeInfo as $val) {
            if ($val['tr_status'] > 1) {
                throw new ParamsInvalidException("该支付已完成");
            }
            $tr_amount += $val['tr_amount'];
        }

        //记录该订单的支付方式
        $extend['tradeId'] = $tradeId;
        $extend['payChannel'] = $pay_init['payChannel'];
        $orderLib->orderExtend($extend);
        $result = $payLib->payInit($pay_init);
        if ($result) {
            $arr = current($result);
            $this->responseJSON($arr);
        }else{
            throw new ServiceException('初始化支付失败');
        }
    }
    public function addMore()
    {
        $params = app()->request()->params();
        $payChannel=app()->request()->params('payChannel');
        $payChannel=empty($payChannel)?6:$payChannel;
        $openid=WXLoginHelper::getWxSession(WXLoginHelper::OPENID);
        if($payChannel==12){
            $openid    = $openid[1]['openid'];
        }
        if(!$openid){
            $openid = isset($_COOKIE['openid']) ? $_COOKIE['openid'] : app()->request()->params('openid');
        }
        $params['buyer_uid']=empty($this->uid)?$params['buyer_uid']:$this->uid;
        if (isset($params['orderDistributionType']) && $params['orderDistributionType'] == 1) {
            //分销订单，获取分销类型（商品分销或者拉新，分享人id）
            list($d_uid, $d_type) = $this->getDistributionData($params);
        } else {
            $d_uid = '';
            $d_type = 0;
        }
        $params['d_uid'] = $d_uid;
        $params['d_type'] = $d_type;
        //下单
        $orderLib = new \Lib\Mall\Order();
        if (empty($params['buyer_uid']) || empty($openid) || (empty($params['aid']) && empty($params['isSelfPickup'])) || empty($params['carts'])) {
            throw new \Exception\ParamsInvalidException("参数不全");
        }

        $uid = $params['buyer_uid'];
        $params['uid'] = $uid;
        $uCartModel = new \Model\Mall\UserCart();
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

                $res['guestContent'] = isset($cart['guestContent']) ? $cart['guestContent'] : '';
                $cartData[] = $res;
                $totalPrice += ($res['ucart_goodsPrice'] * 100 * $res['ucart_goodsNum']) / 100;
                $card_ids[] = $oneCart;
            }
        }
        $params['carts'] = json_encode($cartData);
        $params['totalPrice'] = $totalPrice;
        $resMall = $orderLib->addmore($params);
//        $resMall = $orderLib->add($params);
        $orderId = $resMall;
        if ($orderId) {
            //  pay/get
            $paramsOrder['sn'] = $orderId;
            $paramsOrder['act'] = empty($params['act']) ? '' : $params['act'];
            $orderInfo = $orderLib->detail($paramsOrder);
            $orderInfo = $orderInfo[0];
            if ($orderInfo && $orderInfo['o_pay']>0) {
                //删除购物车中商品
                if ($card_ids) {
                    foreach ($card_ids as $cart) {
                        $data['c_id'] = $cart;
                        $uCartModel->deleteByCid($data);
                    }
                }
                $payLib = new \Lib\Mall\Pay();
                $pay_get['tradeId'] = $orderId;
                $pay_get['subject'] = $orderInfo['g_name'];
                $pay_get['totalAmount'] = $orderInfo['o_pay'] * 100;
                $pay_get['timeout'] = 10;
                $pay_get['from'] = 1;
                $resPay = $payLib->payGet($pay_get);
                if ($resPay) {
                    //  pay/init
                    $pay_init['tradeId'] = $orderId;
                    $pay_init['payChannel'] = $payChannel;
                    $pay_init['openId'] = $openid;

                    $trade = new \Model\Pay\Trade();
                    $config = load_row_configs_trim_prefix('api.Mall');
                    $tradeId = $config['appId'] . '-' . $pay_init['tradeId'];
                    $tradeInfo = $trade->getCartTrade($tradeId);
                    if (!$tradeInfo) {
                        $trade_sn = $trade->getTrade($tradeId);
                        if (!$trade_sn) {
                            throw new ParamsInvalidException("交易信息不存在");
                        }
                        $tradeInfo[] = $trade_sn;
                    }

                    $tr_amount = 0;
                    foreach ($tradeInfo as $val) {
                        if ($val['tr_status'] > 1) {
                            throw new ParamsInvalidException("该支付已完成");
                        }
                        $tr_amount += $val['tr_amount'];
                    }

                    //记录该订单的支付方式
                    $extend['tradeId'] = $tradeId;
                    $extend['payChannel'] = $pay_init['payChannel'];
                    $orderLib->orderExtend($extend);
                    $result = $payLib->payInit($pay_init);
                    if ($result) {
                        $arr = current($result);
                        $this->responseJSON($arr);
                    }else{
                        throw new ServiceException('初始化支付失败');
                    }
                }else{
                    throw new ServiceException('下单失败');
                }
            }else if($orderInfo && $orderInfo['o_pay']==0){
                $this->responseJSON(true);
            }else{
                throw new ServiceException('查询订单失败');
            }
        }else{
            throw new ServiceException('下单失败');
        }
    }

    /**
     * 前端分享时需要用到的
     */
    public function share()
    {
        $url = app()->request()->params('url');
        if (!$url) {
            throw new \Exception\ParamsInvalidException("参数不全");
        }

        $params['timestamp'] = time();
        $params['noncestr'] = $this->getRandChar(32);
        $params['signature'] = $this->sign($params, urldecode($url));
        $params['appId'] = config('app.weChat.appid');

        $this->responseJSON($params);
    }

    private function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $length; $i++) {
            // rand($min,$max)生成介于min和max两个数之间的一个随机整数
            $str .= $strPol[rand(0, $max)];
        }
        return $str;
    }

    private function sign($params, $path)
    {

        $access_token = WxHelper::getAccessToken();

        //获取jsapi_ticket
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $access_token . '&type=jsapi';
        $res = $this->getCurl($url);
        if ($res['errcode'] == 0) {
            $jsapi_ticket = $res['ticket'];
        } else {
            throw new \Exception($res);
        }
        $params['jsapi_ticket'] = $jsapi_ticket;
        $params['url'] = $path;  //'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $sign = $this->MakeSign($params);

        return $sign;
    }

    private function MakeSign($data)
    {
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = $this->ToUrlParams($data);
        $string = sha1($string);

        return $string;
    }

    private function ToUrlParams($data)
    {
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    public function showAccessToken()
    {
        $res = WxHelper::getAccessToken();
        return $this->responseJSON($res);
    }

    /**
     * @Summary :发送微信消息
     * @Author yyb update at 2018/4/13 12:02
     */
    public function sendMsgByWX()
    {
        $msgType = app()->request()->params('msgType');
        $openId = app()->request()->params('openId');
        $proName = app()->request()->params('proName');
        $userName = app()->request()->params('userName');

        $info['userName'] = $userName;
        $info['proName'] = $proName;
        $isSend = $this->_sendMoban($msgType, $openId, $info);

        if ($isSend) {
            $this->responseJSON('ok');
        } else {
            $this->responseJSON('', '', 1, '发送模板失败');
        }
    }

    /**
     * @Summary :发送微信消息
     * @Author yyb update at 2018/4/13 12:02
     */
    private function _sendMsgByWX($msgType, $openId, $info)
    {
        $msgType = app()->request()->params('msgType');
        $openId = app()->request()->params('openId');
        $proName = app()->request()->params('proName');
        $userName = app()->request()->params('userName');

        $info['userName'] = $userName;
        $info['proName'] = $proName;
        $isSend = $this->_sendMoban($msgType, $openId);

        if ($isSend) {
            $this->responseJSON('ok');
        } else {
            $this->responseJSON('', '', 1, '发送模板失败');
        }
    }

    /**
     * @Summary :砍价成功提醒
     * @Author yyb update at 2018/5/14 17:43
     */
    public function sendHelpCutInfo($info, $type)
    {
        if ($type == 'activity') {
            //提醒发起者，砍价已至分水岭
            $this->_sendMoban('sendBeginCutInfo', $info);
        } else if ($type == 'end') {
            //砍价结束，通知帮助砍价者和发起砍价者
            $this->_sendMoban('sendHelpCutInfoEnd', $info);
        }
    }

    /**
     * @Summary :模板消息
     * @param $type
     * @param $user
     * @param $info
     * @Author yyb update at 2018/4/12 14:53
     */
    private function _sendMoban($type, $info)
    {
        $msg = '';
        $AccessToken = WxHelper::getAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $AccessToken;
        if ($type == 'sendBeginCutInfo') {
            $msg1 = $this->_sendBeginCutInfo($info['openId'], $info);
            $res1 = $this->getcurl($url, $msg1);
        } else if ($type == 'sendHelpCutInfoEnd') {
            //砍价结束，通知发起砍价者
            $msg2 = $this->_sendBeginCutInfoEnd($info['openId'], $info);
            $res2 = $this->getcurl($url, $msg2);

            if (isset($info['openIdAll']) && count($info['openIdAll']) > 0) {
                //砍价结束，通知帮助砍价者
                foreach ($info['openIdAll'] as $item) {
                    $msg3 = $this->_sendHelpCutInfoEndAll($item, $info);
                    $res3 = $this->getcurl($url, $msg3);
                }
            }
        }
    }

    //通知发起者，到分水岭了
    private function _sendBeginCutInfo($touser, $info)
    {
        $g_id = $info['g_id'];
        $baseDomain = config('app.baseDomain');
        $info['url'] = "https://{$baseDomain}/html/apph5/cutdownPrice.html#/detail?g_id={$g_id}&openId={$touser}";
        $msg = '{
			"touser":"' . $touser . '",
			"template_id":"' . config('app.weChat.cutSuccess') . '",
			"url":"' . $info['url'] . '",
			"topcolor":"#666666",
			"data":{
				"first": {
					"value":"您的' . $info['g_name'] . '已经被砍到' . $info['ugc_nowPrice'] . '元，再努力一下就可以0元拿到啦。",
					"color":"#ff0000"
				},
				"keyword1":{
					"value":"' . $info['g_name'] . '",
					"color":"#666666"
				},
				"keyword2":{
					"value":"0元免费拿",
					"color":"#666666"
				},
				"remark":{
					"value":"点击分享给好友，继续帮您助力吧！",
					"color":"#666666"
				}
			}
		}';
        return $msg;
    }

    //砍价结束，发起砍价者领奖
    private function _sendBeginCutInfoEnd($touser, $info)
    {
        $g_id = $info['g_id'];
        $ugc_id = $info['ugc_id'];
        $baseDomain = config('app.baseDomain');
        $info['url'] = "https://{$baseDomain}/wx/wx/authNew?type=beginCut&g_id={$g_id}&ugc_id={$ugc_id}";
        $msg = '{
			"touser":"' . $touser . '",
			"template_id":"' . config('app.weChat.cutSuccess') . '",
			"url":"' . $info['url'] . '",
			"topcolor":"#666666",
			"data":{
				"first": {
					"value":"您的' . $info['g_name'] . '已经被砍到0元，免费领取了该宝贝。掌玩将发货到最初填写的地址，请耐心等待。",
					"color":"#ff0000"
				},
				"keyword1":{
					"value":"' . $info['g_name'] . '",
					"color":"#666666"
				},
				"keyword2":{
					"value":"0元免费拿",
					"color":"#666666"
				},
				"remark":{
					"value":"点击这里，告诉好友这个喜悦的消息吧！",
					"color":"#666666"
				}
			}
		}';
        return $msg;
    }

    //砍价结束，通知所有参与者，帮助成功
    private function _sendHelpCutInfoEndAll($touser, $info)
    {
        $baseDomain = config('app.baseDomain');
        $info['url'] = "https://{$baseDomain}/wx/wx/authNew?type=listCut";
        $msg = '{
			"touser":"' . $touser . '",
			"template_id":"' . config('app.weChat.cutSuccess') . '",
			"url":"' . $info['url'] . '",
			"topcolor":"#666666",
			"data":{
				"first": {
					"value":"感谢您的帮助，' . $info['nicknameBegin'] . '已经0元获得了“' . $info['g_name'] . '”商品。",
					"color":"#ff0000"
				},
				"keyword1":{
					"value":"' . $info['g_name'] . '",
					"color":"#666666"
				},
				"keyword2":{
					"value":"0元免费拿",
					"color":"#666666"
				},
				"remark":{
					"value":"点击选取心仪宝贝，一起砍价免费拿吧!",
					"color":"#666666"
				}
			}
		}';
        return $msg;
    }

    public function subscribeSuccess($touser, $info)
    {
        $msg = '{
			"touser":"' . $touser . '",
			"template_id":"' . config('app.weChat.subscribeSuccess') . '",
			"url":"' . $info['url'] . '",
			"topcolor":"#666666",
			"data":{
				"first": {
					"value":"欢迎你关注掌玩",
					"color":"#ff0000"
				},
				"keyword1":{
					"value":"点击下方链接，继续参与活动",
					"color":"#666666"
				},
				"keyword2":{
					"value":"如有疑问，请致电我们",
					"color":"#666666"
				}
			}
		}';
        return $msg;
    }

    public function getSceneQRCodeImg()
    {
        $scene_str = app()->request()->params('scene_str');
//        $sceneImg = $this->_getSceneQRCodeImg($scene_str);
        $type = $gid = $ugc_id = '';

        $listCut = strpos($scene_str, 'listCut') === false ? false : 1;
        $detailCut = strpos($scene_str, 'detailCut') === false ? false : 1;
        $more = strpos($scene_str, ',') === false ? false : 1;

        if ($listCut) {
            //砍价列表：关键字
            $type = 'listCut';
        } else if ($detailCut) {
            //砍价详情
            $type = 'detailCut';
            $arrTemp = explode(',', $scene_str);

            $gid = $arrTemp[1];
        } else if ($more) {
            //帮助砍价：商品id，ugc_id
            $type = 'helpCut';
            $arrTemp = explode(',', $scene_str);
            $gid = $arrTemp[0];
            $ugc_id = $arrTemp[1];
        } else {
            //发起砍价、商品详情：商品id
            $type = 'beginCut';
            $gid = $scene_str;
        }

        //生成链接
        $url = $this->_authNew($type, $gid, $ugc_id);
        $userInfo['url'] = $url;

        //发送模板消息
        //Todo 去掉默认值
        $openId = $openId = isset($_COOKIE['openid']) ? $_COOKIE['openid'] : 'o164X0nZRkSetMrWW0j_9GMdcyR4';
        $userInfo['openId'] = $openId;
        $this->_sendMoban('subscribeSuccess', $userInfo);
        if (empty($openId)) {
            throw new \Exception\ParamsInvalidException("缺少openId");
        }
        $sceneImg = $this->_getTempSceneQRCodeImg($scene_str);

        $sceneImg['userInfo'] = $userInfo;
        $this->responseJSON($sceneImg);
    }

    public function getSceneQRCodeImgByTicket()
    {
        $ticket = app()->request()->params('ticket');
        $sceneImg = $this->_exchangeQRCodeImg($ticket);
        $this->responseJSON($sceneImg);
    }

    /**
     * @Summary :永久二维码
     * @param $scene_str
     * @return mixed
     * @throws \Exception
     * @Author yyb update at 2018/4/13 9:46
     */
    private function _getSceneQRCodeImg($scene_str)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . WxHelper::getAccessToken();
        $data = '{"action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str": "' . $scene_str . '"}}}';
        $res = $this->getcurl($url, $data);
        return $res;
    }

    /**
     * @Summary :临时二维码
     * @param $scene_str
     * @return mixed
     * @throws \Exception
     * @Author yyb update at 2018/4/13 9:46
     */
    private function _getTempSceneQRCodeImg($scene_str)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . WxHelper::getAccessToken();
        $data = '{"expire_seconds": 2592000, "action_name": "QR_STR_SCENE", "action_info": {"scene": {"scene_str": "' . $scene_str . '"}}}';
        $res = $this->getcurl($url, $data);
        return $res;
    }

    private function _exchangeQRCodeImg($ticket)
    {
        $ticket = urlencode($ticket);
        $url = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $ticket;
        $res = $this->getcurl($url);
        return $res;
    }

    public function getCurl($url, $data = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        // 这一句是最主要的
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, TRUE);
    }

    /**
     * @Summary :
     * @return mixed
     * @Author yyb update at 2018/5/7 19:36
     */
    function getUserInfo()
    {
        $openId = isset($_COOKIE['openid']) ? $_COOKIE['openid'] : app()->request()->params('openid');
        $AccessToken = WxHelper::getAccessToken();

        if (empty($openId) || empty($AccessToken)) {
            throw new \Exception\ParamsInvalidException("参数不全");
        }

        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $AccessToken . "&openid=" . $openId . "&lang=zh_CN";
        $this->_wx_api_action = 'GetUserInfo';
        $return = $this->getcurl($url);
        if (isset($return['errcode']) && $return['errcode'] > 0) {
            $AccessToken = WxHelper::getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $AccessToken . "&openid=" . $openId . "&lang=zh_CN";
            $this->_wx_api_action = 'GetUserInfo';
            $return = $this->getcurl($url);
        }


        //存入数据库
        if ($return['subscribe'] == 1) {
            $itemOpenidQuery = new \Lib\Mall\Goods();
            $addData['uo_subscribe'] = $return['subscribe'];
            $addData['uo_openId'] = $openId;
            $addData['uo_nickname'] = $return['nickname'];
            $addData['uo_sex'] = $return['sex'];
            $addData['uo_headimgurl'] = $return['headimgurl'];
            $addData['uo_subscribe_time'] = date('Y-m-d H:i:s', $return['subscribe_time']);

            $itemOpenidQuery->itemOpenidQuery($addData);
        }

        $this->responseJSON($return);
    }

//    public function TestCreatMenu()
//    {
//        $arr_sub1[] = array(
//            'type' => 'view',
//            'name' => '我的订单',
//            'url' => 'http://twx.pingoing.cn/order/orderlist?newlogin=1'
//        );
//
//        $arr_sub3[] = array(
//            'type' => 'click',
//            'name' => '新品提前预订',
//            'key' => 'menu_1'
//        );
//
//        $arr_sub3[] = array(
//            'type' => 'click',
//            'name' => '在线客服',
//            'key' => 'menu_2'
//        );
//
//        $arr_sub3[] = array(
//            'type' => 'view',
//            'name' => '发货承诺',
//            'url' => 'http://mp.weixin.qq.com/s?__biz=MzIzMDA1MDgzNQ==&mid=401094339&idx=1&sn=29df073c0561b5acbe802c5a1588bf1f'
//        );
//        $arr_sub3[] = array(
//            'type' => 'view',
//            'name' => 'APP下载',
//            'url' => 'http://twx.pingoing.cn/weixin/app'
//        );
//
//        $arr_sub3[] = array(
//            'type' => 'view',
//            'name' => '拼团流程',
//            'url' => 'http://mp.weixin.qq.com/s?__biz=MzIzMDA1MDgzNQ==&mid=400991717&idx=1&sn=21b75ed491e777de9204349a264cac9b'
//        );
//
//        $arr_sub2[] = array(
//            'type' => 'view',
//            'name' => '拼一下',
//            'url' => 'http://twx.pingoing.cn?newlogin=1'
//        );
//        $arr_sub2[] = array(
//            'type' => 'view',
//            'name' => '一元夺宝',
//            'url' => 'http://twx2.pingoing.cn'
//        );
//
//        $arr[] = array(
//            'type' => 'view',
//            'name' => '招商',
//            'url' => 'http://www.pingoing.cn'
//        );
//        $arr[] = array(
//            'name' => '商城入口',
//            'sub_button' => $arr_sub2
//        );
//
//        $arr[] = array(
//            'name' => '在线客服',
//            'sub_button' => $arr_sub3
//        );
//
//        $menu['button'] = $arr;
//        $this->creatMenu($menu);
//    }
//
//    /*** 生成微信菜单 ***/
//    function creatMenu($arr = array())
//    {
//        $token = $this->getAccessToken('new');
//        foreach ($arr['button'] as $k => $v) {
//            foreach ($v as $K => $V) {
//                if ($K == 'name') {
//                    $arr['button'][$k][$K] = urlencode($V);
//                }
//                if ($K == 'sub_button') {
//                    foreach ($V as $m => $n) {
//                        $arr['button'][$k][$K][$m]['name'] = urlencode($n['name']);
//                    }
//                }
//            }
//        }
//        $msg = urldecode(json_encode($arr));
//        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=" . $token;
//        $res = $this->getCurl($url, $msg, 0);
//        print_r($res);
//    }
}
