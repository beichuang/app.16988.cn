<?php

/**
 * 拍品结拍剩余30分钟-给所有参与拍卖的用户发送消息提醒
 */
namespace Cli\Worker;

use Framework\Helper\FileHelper;
use Lib\Common\AppMessagePush;
use Lib\User\UserSms;

class AuctionEndNotice
{
    const END_REMAINING_30_M = 'endRemaining30m';
    const SMS_TEMPLATE_TYPE = '';
    private $db = null;

    public function __construct()
    {
        $this->db = app('mysqlbxd_mall_common');
    }

    public function run()
    {
        while (true) {
            $appMessageData = [];
            $smsMessageData = [];
            $auctionIds = [];
            //获取结拍时间不足30分钟且未发送消息的拍品记录
            $endRemaining30mData = $this->fetchData();
            if ($endRemaining30mData) {
                foreach ($endRemaining30mData as $item) {
                    $auctionId = $item['a_id'];
                    $auctionIds[] = $auctionId;
                    //获取出价的所有用户id
                    $userIds = $this->getUserIds($auctionId);
                    //根据用户id获取手机号
                    if ($userIds) {
                        $appMessageData[] = [
                            'a_id' => $auctionId,
                            'uids' => $userIds,
                            'messageTitle' => "您参与的{$item['a_name']}拍卖，还有30分钟结拍，点击去出价现场查看",
                            'messageContent' => "您参与的{$item['a_name']}拍卖，还有30分钟结拍，点击去出价现场查看",
                            'imageUrl' => FileHelper::getFileUrl($item['a_surfaceImg'], 'mall_auction_images')
                        ];
                        $smsMessageData[] = [
                            'a_id' => $auctionId,
                            'messageText' => $item['a_name'],
                            'uids' => $userIds
                        ];
                    }
                }

                $this->setAuctionMessageSendStatus($auctionIds);
                $this->sendMessage($appMessageData, $smsMessageData);
            }
            exitTask('03:00:00', '03:02:00');
            sleep(60);
        }
    }

    private function sendMessage($appMessageData, $smsMessageData)
    {
        if ($appMessageData) {
            foreach ($appMessageData as $appItem) {
                if (is_array($appItem['uids'])) {
                    $userIds = implode(',', $appItem['uids']);
                } else {
                    $userIds = $appItem['uids'];
                }
                //用户id集合、消息标题、推送内容预览、消息内容、消息类型
                AppMessagePush::push($userIds, $appItem['messageTitle'], $appItem['messageContent'],
                    ['sid' => $appItem['a_id'], 'utype' => 'auction','image' => $appItem['imageUrl']], AppMessagePush::PUSH_TYPE_AUCTION_END_SOON);
            }
        }

        if ($smsMessageData) {
            $sms=new \Lib\Common\Sms\Sms();
            foreach ($smsMessageData as $smsItem) {
                foreach ($smsItem['uids'] as $tmpUid){
                    $sms->sendByUidTpl($tmpUid,\Lib\Common\Sms\SmsInterface::tpl_auction_end,[
                        'a_name'=>$smsItem['messageText'],
                    ]);
                }
            }
        }
    }

    private function fetchData()
    {
        $sql = "SELECT * FROM `auction` a INNER JOIN `auction_message_send` ams ON	a.a_id = ams.a_id
WHERE a.a_auditStatus=1 AND ams.`am_messageType`='" . static::END_REMAINING_30_M . "' AND ams.`am_sendStatus`=0 
AND `a_endDate` >= NOW() AND `a_endDate` <= DATE_ADD(NOW(), INTERVAL +30 MINUTE)";
        $data = $this->db->select($sql);
        return $data;
    }

    private function getUserIds($auctionId)
    {
        $sql = 'SELECT DISTINCT	`abr_userId` FROM `auction_bid_record` WHERE a_id=:auctionId;';
        $data = $this->db->select($sql, [':auctionId' => $auctionId]);
        if ($data) {
            return array_column($data, 'abr_userId');
        }

        return [];
    }

    private function setAuctionMessageSendStatus($auctionIds, $type = self::END_REMAINING_30_M)
    {
        if ($auctionIds) {
            $sql = "UPDATE auction_message_send SET am_sendStatus=1 WHERE FIND_IN_SET(a_id, :auctionIds) AND am_messageType='{$type}'";
            $this->db->query($sql, [':auctionIds' => implode(',', $auctionIds)]);
        }
    }
}
