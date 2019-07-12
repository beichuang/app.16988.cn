<?php

/**
 * 拍品支付提醒-给订单生成后第一天、第二天未支付的订单用户发送提醒
 */
namespace Cli\Worker;

use Framework\Helper\FileHelper;
use Lib\Common\AppMessagePush;
use Lib\User\UserSms;

class AuctionOrderPayNotice
{
    const UN_PAY_FIRST_DAY = 'unPayFirstDay';
    const UN_PAY_SECOND_DAY = 'unPaySecondDay';
    private $db = null;
    private $mall_user_db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_common');
        $this->mall_user_db = app('mysqlbxd_mall_user');
    }

    public function run()
    {
        while (true) {
            //每天12：00提醒，在12：00至12：30之间运行
            $time = date('H:i:s');
            if ($time >= '12:00:00' && $time <= '12:30:00') {
                $appMessageData = [];
                $smsMessageData = [];

                //获取订单生成时间为昨天或前天，且订单支付提醒未发送的订单
                $unPayMessageData = $this->fetchData();
                if ($unPayMessageData) {
                    foreach ($unPayMessageData as $item) {
                        $auctionId = $item['a_id'];
                        //获得订单支付状态
                        $orderData = $this->getOrderData($item['a_orderSn']);
                        if ($orderData) {
                            $orderStatus = $orderData['o_status'];
                            if ($orderStatus != 0) {
                                //非未支付状态
                                $this->setAuctionMessageSendStatus($auctionId, $item['am_messageType'], 2);
                                if($item['am_messageType'] == static::UN_PAY_FIRST_DAY) {
                                    $this->setAuctionMessageSendStatus($auctionId, static::UN_PAY_SECOND_DAY, 2);
                                }
                            } else {
                                $userId = $item['a_orderUserId'];
                                $appMessageData[] = [
                                    'a_orderSn' => $item['a_orderSn'],
                                    'uids' => $userId,
                                    'messageTitle' => "您拍的{$item['a_name']}尚未支付，前往我的拍卖-已成交，请在规定时间内完成支付，宝贝很快到您身边~",
                                    'messageContent' => "您拍的{$item['a_name']}尚未支付，前往我的拍卖-已成交，请在规定时间内完成支付，宝贝很快到您身边~",
                                    'imageUrl' => FileHelper::getFileUrl($item['a_surfaceImg'], 'mall_auction_images')
                                ];
                                $smsMessageData[] = [
                                    'a_id' => $auctionId,
                                    'messageText' => $item['a_name'],
                                    'uids' => $userId
                                ];
                                $this->setAuctionMessageSendStatus($auctionId, $item['am_messageType']);
                            }
                        }
                    }

                    $this->sendMessage($appMessageData, $smsMessageData);
                }
                sleep(180);
            } else {
                exitTask('03:00:00', '03:02:00');
                sleep(60);
            }
        }
    }

    private function sendMessage($appMessageData, $smsMessageData)
    {
        if ($appMessageData) {
            foreach ($appMessageData as $appItem) {
                //用户id集合、消息标题、推送内容预览、消息内容、消息类型
                AppMessagePush::push($appItem['uids'], $appItem['messageTitle'], $appItem['messageContent'],
                    ['sid' => $appItem['a_orderSn'], 'utype' => 'buyer','image' => $appItem['imageUrl']], AppMessagePush::PUSH_TYPE_AUCTION_ORDER_UN_PAY);
            }
        }

        if ($smsMessageData) {

            $sms=new \Lib\Common\Sms\Sms();
            foreach ($smsMessageData as $smsItem) {
                $sms->sendByUidTpl($smsItem['uids'],\Lib\Common\Sms\SmsInterface::tpl_auction_to_pay,[
                    'a_name'=>$smsItem['messageText'],
                ]);
            }
        }
    }

    private function fetchData()
    {
        $today = date("Y-m-d");
        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $beforeYesterday = date("Y-m-d", strtotime("-2 day"));
        $sql = "SELECT a.a_id,a.a_name,a_orderSn,a_orderUserId, ams.`am_messageType`,a.a_surfaceImg FROM `auction` a INNER JOIN `auction_message_send` ams ON	a.a_id = ams.a_id
WHERE a.a_orderCreateStatus=1 
AND ((a.a_orderCreateDate >='{$yesterday}' AND a.a_orderCreateDate < '{$today}' AND  ams.`am_messageType`='" . static::UN_PAY_FIRST_DAY . "' ) 
OR (a.a_orderCreateDate >='{$beforeYesterday}' AND a.a_orderCreateDate < '{$yesterday}' AND ams.`am_messageType`='" . static::UN_PAY_SECOND_DAY . "')) 
AND ams.`am_sendStatus`=0";
        $data = $this->db->select($sql);
        return $data;
    }

    private function setAuctionMessageSendStatus($auctionIds, $messageType, $sendStatus = 1)
    {
        $sql = "UPDATE auction_message_send SET am_sendStatus=:sendStatus WHERE FIND_IN_SET(a_id, :auctionIds)";
        if ($messageType) {
            $sql .= " AND am_messageType='{$messageType}'";
        }
        $this->db->query($sql, [':auctionIds' => $auctionIds, ':sendStatus' => $sendStatus]);
    }

    private function getOrderData($orderSn)
    {
        $sql = 'SELECT o_id, o_status FROM `order` WHERE o_sn=:sn AND `o_isDelete`=0';
        return $this->mall_user_db->fetch($sql, [':sn' => $orderSn]);
    }
}
