<?php

namespace Cli\Worker;

use Framework\Helper\FileHelper;
use Lib\Common\AppMessagePush;
use Lib\User\UserSms;
use Rest\Mall\Facade\AuctionManager;
use Rest\Mall\Facade\ItemManager;
use Rest\Mall\Facade\OrderManager;

class OrderStatusChangeNotice {

    private $mainQueuePrefix = null;
    private $queueFrequency = null;
    private $queueFlag = null;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->mainQueuePrefix = config('data_change_notice_queue.redis_queue_prefix', 'api_user_queue_User_Data_Change_Notice_Key_');
        $this->queueFlag = config('data_change_notice_queue.interface.order.appMessagePushQueue');
        $this->queueFrequency = config('data_change_notice_queue.interface.order.frequency', 200000);
    }

    /**
     * 入口函数
     */
    public function run() {
        $restartStart = config('data_change_notice_queue.interface.order.workrRestart.0');
        $restartEnd = config('data_change_notice_queue.interface.order.workrRestart.1');
        while (true) {
            if ($message = $this->popQueue()) {
                try {
                    $changeType = $message['changeType'];
                    $o_id = $message['o_id'];
                    $orderInfo = $message['orderInfo'];
                    $x_forwoarded_url = isset($message['x_forwoarded_url']) ? $message['x_forwoarded_url'] : '';
                    $this->appMessagePush($changeType, $o_id, $orderInfo, $x_forwoarded_url);
                } catch (\Exception $e) {
                    wlog_exception($e);
                }
            }
            exitTask($restartStart, $restartEnd);
            usleep($this->queueFrequency);
        }
    }

    /**
     * 取队列
     * @return mixed|boolean
     */
    private function popQueue() {
        $raw = app('redis')->rPop($this->mainQueuePrefix . $this->queueFlag);
        if ($raw) {
            $message = unserialize($raw);
            if (is_array($message) && isset($message['changeType']) && isset($message['o_id'])) {
                return $message;
            }
        }
        return false;
    }

    /**
     * 推送APP消息
     * @param unknown $changeType
     * @param unknown $o_id
     * @param unknown $orderInfo
     * @return boolean
     */
    private function appMessagePush($changeType, $o_id, $orderInfo, $x_forwoarded_url) {
        if (!isset($changeType) || !$o_id || !$orderInfo || !is_array($orderInfo) || empty($orderInfo)) {
            return false;
        }
        $o_sn = $orderInfo['o_sn'];
        $buyerUid = $orderInfo['u_id'];
        $salesUid = $orderInfo['o_salesUid'];
        $time = $orderInfo['o_updateDate'];
        $reason = $orderInfo['o_cancelReason'];
        $payMoney = $orderInfo['o_pay'];
        $goodsName = $orderInfo['g_name'];
        $type = $orderInfo['g_type'];
        $messageTitle = '';
        $uids = '';
        $msgData = [];
        $smsData = []; //短信消息数据

        switch ($changeType) {
            case 13:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "发货通知",
                        'messageContent' => "您的订单，订单号{$o_sn}卖家已发货，宝贝正飞速向您驶来，请保持手机畅通"
                    ],
                    'saler' => []
                ];

                break;

            case 14:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "订单取消",
                        'messageContent' => "您的订单，订单号{$o_sn}已取消，取消原因：{$reason}"
                    ],
                    'saler' => [
                        'uids' => $salesUid,
                        'messageTitle' => "订单取消",
                        'messageContent' => "您的订单，订单号{$o_sn}买家已取消，取消原因：{$reason}"
                    ]
                ];

                break;

            case 17:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "订单完成",
                        'messageContent' => "您的订单，订单号{$o_sn}交易已完成。"
                    ],
                    'saler' => [
                        'uids' => $salesUid,
                        'messageTitle' => "订单完成",
                        'messageContent' => "您的订单，订单号{$o_sn}已交易完成。"
                    ]
                ];

                break;

            case 20:
                if($type == 4) {  //拍品
                    $msgData = [
                        'buyer' => [
                            'uids' => $buyerUid,
                            'messageTitle' => "支付通知",
                            'messageContent' => "恭喜，您拍的{$goodsName}已到手，前往我的拍卖-已成交，请在规定时间内完成支付。"
                        ],
                        'saler' => [
                            'uids' => $salesUid,
                            'messageTitle' => "成交通知",
                            'messageContent' => "恭喜，您的拍品{$goodsName}有人拍下了，快去看看~"
                        ]
                    ];
                    $smsData = [
                        'buyer' => [
                            'uids' => $buyerUid,
                            'messageText' => $goodsName,

                        ],
                        'saler' => [
                            'uids' => $salesUid,
                            'messageText' => $goodsName,
                        ]
                    ];
                }else {
                    $msgData = [
                        'buyer' => [
                            'uids' => $buyerUid,
                            'messageTitle' => "支付通知",
                            'messageContent' => "您的订单，订单号{$o_sn}已下单成功，总价为{$payMoney}元，请在半小时内付款，超出时间订单将自动关闭。"
                        ],
                        'saler' => [
                            'uids' => $salesUid,
                            'messageTitle' => "成交通知",
                            'messageContent' => "有人拍下了您的商品：{$goodsName}，订单号：{$o_sn}，等待买家付款，您也可以直接跟买家联系哦。"
                        ]
                    ];
                }

                break;

            case 21:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "支付成功",
                        'messageContent' => "您的订单，订单号{$o_sn}已支付成功，卖家会于72小时内发货。"
                    ],
                    'saler' => [
                        'uids' => $salesUid,
                        'messageTitle' => "成交通知",
                        'messageContent' => "有人下单购买了您的商品：{$goodsName}，订单号：{$o_sn}，请您于72小时内妥善包装发货，并填写物流单号哦。"
                    ]
                ];

                break;

            case 23:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "退款通知",
                        'messageContent' => "您的订单，订单号{$o_sn}已申请退款，退款原因：{$reason}.请等待卖家答复"
                    ],
                    'saler' => [
                        'uids' => $salesUid,
                        'messageTitle' => "退款通知",
                        'messageContent' => "您的订单，订单号{$o_sn} 买家已申请退款，退款原因：{$reason}，请跟买家联系了解原因并妥善处理。"
                    ]
                ];

                break;

            case 24:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "退款通知",
                        'messageContent' => "您的订单，订单号{$o_sn}已申请退货，退货原因：{$reason}"
                    ],
                    'saler' => [
                        'uids' => $salesUid,
                        'messageTitle' => "退款通知",
                        'messageContent' => "您的订单，订单号{$o_sn} 买家已申请退货，退货原因：{$reason}，请跟买家联系了解原因并妥善处理。"
                    ]
                ];

                break;

            case 25:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "退款通知",
                        'messageContent' => "您的订单，订单号{$o_sn}卖家已同意退货，请妥善包装寄回给卖家"
                    ],
                    'saler' => []
                ];

                break;

            case 26:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "订单关闭",
                        'messageContent' => "您的订单，订单号{$o_sn}退货已完成，订单已关闭"
                    ],
                    'saler' => [
                        'uids' => $salesUid,
                        'messageTitle' => "订单关闭",
                        'messageContent' => "您的订单，订单号{$o_sn}退货已完成，订单已关闭"
                    ]
                ];

                break;

            case 27:
                $msgData = [
                    'buyer' => [
                        'uids' => $buyerUid,
                        'messageTitle' => "订单关闭",
                        'messageContent' => "您的订单，订单号{$o_sn}退款已完成，订单已关闭"
                    ],
                    'saler' => [
                        'uids' => $salesUid,
                        'messageTitle' => "订单关闭",
                        'messageContent' => "您的订单，订单号{$o_sn}退款已完成，订单关闭。"
                    ]
                ];

                break;
        }
        if ($msgData) {
            foreach ($msgData as $k => $v) {
                if ($v) {
                    $info = array(
                        'utype' => $k,
                        'sid' => $o_sn,
                        'image' => $this->getOrderImageUrl($o_sn)
                    );
                    AppMessagePush::push($v['uids'], $v['messageTitle'], "{$v['messageContent']}，{$time}", $info, AppMessagePush::PUSH_TYPE_ORDER_STATUS_CHANGE);
                }
            }
        }

        if($smsData) {
            $sms=new \Lib\Common\Sms\Sms();
            $userSms = new UserSms();
            foreach ($smsData as $role=>$smsItem) {
                if($role=='buyer'){
                    //buyer
                    $sms->sendByUidTpl($smsItem['uids'],\Lib\Common\Sms\SmsInterface::tpl_auction_finish,[
                        'goodsName'=>$smsItem['messageText'],
                    ]);
                }else{
                    //saler
                    $sms->sendByUidTpl($smsItem['uids'],\Lib\Common\Sms\SmsInterface::tpl_auction_saler_bought,[
                        'goodsName'=>$smsItem['messageText'],
                    ]);
                }
            }
        }
    }

    private function getOrderImageUrl($sn)
    {
        $imageUrl = '';
        $orderData = OrderManager::getOrderBySn($sn);
        if ($orderData) {
            switch ($orderData['g_type']) {
                case 1: //商品
                case 5: //描述商品
                    $goodsData = ItemManager::getItemById($orderData['g_id']);
                    if ($goodsData) {
                        $goodsSurfaceImgJson = $goodsData[0]['g_surfaceImg'];
                        $goodsSurfaceImg = json_decode($goodsSurfaceImgJson, true);
                        if (!empty($goodsSurfaceImg['gi_img'])) {
                            $imageUrl = FileHelper::getFileUrl($goodsSurfaceImg['gi_img']);
                        } else {
                            $goodsImageData = ItemManager::getItemImageById($orderData['g_id']);
                            if ($goodsImageData) {
                                $imageUrl = FileHelper::getFileUrl($goodsImageData[0]['gi_img'],'mall_goods_attr_images');
                            }
                        }
                    }
                    break;
                case 4:
                    //拍品
                    $auctionData = AuctionManager::getItemById($orderData['g_id']);
                    if ($auctionData) {
                        $auctionSurfaceImg = $auctionData['a_surfaceImg'];
                        if (!empty($auctionSurfaceImg)) {
                            $imageUrl = FileHelper::getFileUrl($auctionSurfaceImg, 'mall_auction_images');
                        } else {
                            $auctionImageData = AuctionManager::getImageById($orderData['g_id']);
                            if ($auctionImageData) {
                                $imageUrl = FileHelper::getFileUrl($auctionImageData[0]['gi_img']);
                            }
                        }
                    }
                    break;
            }
        }

        return $imageUrl;
    }

}
