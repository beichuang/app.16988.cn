<?php

/**
 * 定制消息通知
 */
namespace Cli\Worker;


use Framework\Helper\FileHelper;
use Lib\Common\AppMessagePush;
use Lib\User\UserSms;
use Model\User\UserSpeciality;
use Rest\Mall\Facade\CustomManager;

class CustomNotice
{
    private $db = null;
    private $customLib = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->mainQueuePrefix = config('data_change_notice_queue.redis_queue_prefix', 'api_user_queue_User_Data_Change_Notice_Key_');
        $this->queueFlag = config('data_change_notice_queue.interface.custom.appMessagePushQueue');
        $this->db = app('mysqlbxd_mall_common');
        $this->customLib = new \Lib\Mall\Custom();
    }

    public function run()
    {
        while (true) {
            //处理消息队列里的通知
            if ($message = $this->popQueue()) {
                $appMessageData = $this->getQueueNoticeData($message);
                $this->sendMessage($appMessageData);
            }

            //选稿提醒通知
            //每天12：00提醒
            $time = date('H:i:s');
            if ($time >= '12:00:00' && $time <= '12:10:00') {
                list($appMessageData, $smsMessageData) = $this->getSelectNoticeData();
                $this->sendMessage($appMessageData, $smsMessageData);
            }
            exitTask('03:00:00', '03:02:00');
            sleep(180);
        }
    }

    private function getQueueNoticeData($message)
    {
        $appMessageData = [];
        if (empty($message['type'])) {
            wlog('定制消息队列type为空');
            return $appMessageData;
        }
        switch ($message['type']) {
            case 'addCustom':
                $customData = $this->getCustomData($message);
                if (empty($customData)) {
                    break;
                }
                //投稿截止时间
                $submitEndDateFormat = date('m-d H:i', strtotime($customData['c_submit_endDate']));
                //定制用户id
                $designatedUserId = $customData['c_designatedUserId'];
                if ($designatedUserId) {
                    $appMessageData[] = [
                        'utype' => 'custom',
                        'sid' => $customData['c_id'],
                        'uids' => $designatedUserId,
                        'messageTitle' => "恭喜，有人想定制您的作品，“{$customData['c_title']}”，点击投稿赚佣金",
                        'messageContent' => "投稿时间截止到{$submitEndDateFormat}，请把握好时间哦~",
                        'imageUrl' => $this->getImageUrl($customData['c_id']),
                    ];
                }
                //获取擅长领域推送用户
                $userIds = [];
                $userSpecialityData = (new UserSpeciality())->getAllByCategoryId($customData['c_first_level_categoryId']);
                if ($userSpecialityData) {
                    $userIds = array_column($userSpecialityData, 'u_id');
                }
                if ($userIds) {
                    if ($designatedUserId && in_array($designatedUserId, $userIds)) {
                        $userIds = array_diff($userIds, [$designatedUserId]);
                    }
                    if ($userIds) {
                        //检查是否完成艺术家或机构认证
                        $certModel = new \Model\User\Certification();
                        $certList = $certModel->getCertListByUserIds($userIds);
                        if ($certList) {
                            $certUserIds = [];
                            foreach ($certList as $certItem) {
                                if ($certItem['uce_status'] == 1 && in_array($certItem['uce_isCelebrity'], [1, 2])) {
                                    $certUserIds[] = $certItem['u_id'];
                                }
                            }
                            if ($certUserIds) {
                                $appMessageData[] = [
                                    'utype' => 'custom',
                                    'sid' => $customData['c_id'],
                                    'uids' => $certUserIds,
                                    'messageTitle' => "您收到一个定制推送，“{$customData['c_title']}”，点击投稿赚佣金",
                                    'messageContent' => "投稿时间截止到{$submitEndDateFormat}，请把握好时间哦~",
                                    'imageUrl' => $this->getImageUrl($customData['c_id']),
                                ];
                            }
                        }
                    }
                }
                break;
            case 'customOrderSave':
                $customData = $this->getCustomData($message);
                if (empty($customData)) {
                    break;
                }
                $customGoodsData = CustomManager::getSelectedCustomGoods($customData['c_id']);
                if (empty($customGoodsData)) {
                    break;
                }
                //定制发布者
                $buyerUid = $customData['c_createUserId'];
                $appMessageData[] = [
                    'utype' => 'buyer',
                    'sid' => $customData['c_orderSn'],
                    'uids' => $buyerUid,
                    'messageTitle' => "您发布的定制“{$customData['c_title']}”已生成订单，请等待发货~",
                    'messageContent' => "您发布的定制“{$customData['c_title']}”已生成订单，请等待发货~",
                    'imageUrl' => $this->getImageUrl($customGoodsData['cg_id'], 2),
                ];
                //投稿被选中者
                $salesUid = $customData['c_selectedUserId'];
                $appMessageData[] = [
                    'utype' => 'saler',
                    'sid' => $customData['c_orderSn'],
                    'uids' => $salesUid,
                    'messageTitle' => "您投稿的定制“{$customData['c_title']}”已被发布者选中，请尽快发货哦~",
                    'messageContent' => "您投稿的定制“{$customData['c_title']}”已被发布者选中，请尽快发货哦~",
                    'imageUrl' => $this->getImageUrl($customGoodsData['cg_id'], 2),
                ];
                break;

        }

        return $appMessageData;
    }

    private function getSelectNoticeData()
    {
        $appMessageData = [];
        $smsMessageData = [];
        $selectCustomData = $this->getSelectCustomData();
        if ($selectCustomData) {
            foreach ($selectCustomData as $selectCustomItem) {
                $selectNoticeJson = $selectCustomItem['c_selectNotice'];
                if ($selectNoticeJson) {
                    $selectNoticeData = json_decode($selectNoticeJson, true);
                } else {
                    $selectNoticeData = [];
                }
                $selectEndDateFormat = date('m-d', strtotime($selectCustomItem['selectCustomGoodsEndDate']));
                $isSendMessage = false;
                switch ($selectCustomItem['selectCustomGoodsDay']) {
                    case 0:
                        if (empty($selectNoticeData['first'])) {
                            $isSendMessage = true;
                            $selectNoticeData['first'] = 1;
                            $appMessageData[] = [
                                'utype' => 'custom',
                                'sid' => $selectCustomItem['c_id'],
                                'uids' => $selectCustomItem['c_createUserId'],
                                'messageTitle' => "您发布的定制“{$selectCustomItem['c_title']}”已进入选稿阶段，点击选稿",
                                'messageContent' => "请在{$selectEndDateFormat}前选稿，便于艺术家早日将宝贝邮寄到您的身边哦~",
                                'imageUrl' => $this->getImageUrl($selectCustomItem['c_id']),
                            ];
                        }
                        break;
                    case 1:
                        if (empty($selectNoticeData['second'])) {
                            $isSendMessage = true;
                            $selectNoticeData['second'] = 1;
                            $appMessageData[] = [
                                'utype' => 'custom',
                                'sid' => $selectCustomItem['c_id'],
                                'uids' => $selectCustomItem['c_createUserId'],
                                'messageTitle' => "您发布的定制“{$selectCustomItem['c_title']}”距离选稿结束还有2天，点击选稿",
                                'messageContent' => "请在{$selectEndDateFormat}前选稿，便于艺术家早日将宝贝邮寄到您的身边哦~",
                                'imageUrl' => $this->getImageUrl($selectCustomItem['c_id']),
                            ];
                        }
                        break;
                    case 2:
                        if (empty($selectNoticeData['third'])) {
                            $isSendMessage = true;
                            $selectNoticeData['third'] = 1;
                            $appMessageData[] = [
                                'utype' => 'custom',
                                'sid' => $selectCustomItem['c_id'],
                                'uids' => $selectCustomItem['c_createUserId'],
                                'messageTitle' => "您发布的定制“{$selectCustomItem['c_title']}”距离选稿结束只有1天，点击选稿",
                                'messageContent' => "请在{$selectEndDateFormat}前选稿，便于艺术家早日将宝贝邮寄到您的身边哦~",
                                'imageUrl' => $this->getImageUrl($selectCustomItem['c_id']),
                            ];
                        }
                        break;
                    default:
                        continue;
                }

                if ($isSendMessage) {
                    $date = date('m-d', strtotime($selectCustomItem['selectCustomGoodsEndDate']));
                    $smsMessageData[] = [
                        'messageText' => $date,
                        'uids' => $selectCustomItem['c_createUserId']
                    ];

                    //更改状态
                    $this->customLib->update($selectCustomItem['c_id'], ['c_selectNotice' => json_encode($selectNoticeData)]);
                }
            }
        }

        return [$appMessageData, $smsMessageData];
    }

    /**
     * 取队列
     * @return mixed|boolean
     */
    private function popQueue()
    {
        $raw = app('redis')->rPop($this->mainQueuePrefix . $this->queueFlag);
        if ($raw) {
            $message = unserialize($raw);
            if (is_array($message)) {
                return $message;
            }
        }
        return false;
    }

    private function sendMessage($appMessageData, $smsMessageData = [])
    {
        if ($appMessageData) {
            foreach ($appMessageData as $appItem) {
                if (is_array($appItem['uids'])) {
                    $userIds = implode(',', $appItem['uids']);
                } else {
                    $userIds = $appItem['uids'];
                }

                if ($appItem['utype'] == 'custom') {
                    $type = AppMessagePush::PUSH_TYPE_CUSTOM;
                } else {
                    $type = AppMessagePush::PUSH_TYPE_ORDER_STATUS_CHANGE;
                }
                //用户id集合、消息标题、推送内容预览、消息内容、消息类型
                AppMessagePush::push($userIds, $appItem['messageTitle'], $appItem['messageContent'],
                    ['sid' => $appItem['sid'], 'utype' => $appItem['utype'], 'image' => $appItem['imageUrl']], $type);
            }
        }

        if ($smsMessageData) {
            $sms=new \Lib\Common\Sms\Sms();
            foreach ($smsMessageData as $smsItem) {
                $sms->sendByUidTpl($smsItem['uids'],\Lib\Common\Sms\SmsInterface::tpl_custom_select,[
                    'date'=>$smsItem['messageText'],
                ]);
            }
        }
    }

    private function getCustomData($message)
    {
        $customData = [];
        if (empty($message['customId'])) {
            wlog('发送定制消息时，参数customId不存在');
        } else {
            $customId = $message['customId'];
            $customData = $this->customLib->getOneById($customId);
            if (empty($customData)) {
                wlog('发送定制消息时,customId:' . $customId . '的定制不存在');
            }
        }
        return $customData;
    }

    private function getSelectCustomData()
    {
        $sql = 'SELECT *, datediff(now(),`c_submit_endDate`) AS selectCustomGoodsDay,date_add(`c_submit_endDate`, INTERVAL 3 DAY) AS selectCustomGoodsEndDate FROM `custom`	WHERE `c_status`=30 AND `c_submit_endDate` <= NOW() AND date_add(`c_submit_endDate`, INTERVAL 3 DAY) > NOW() AND `c_submitCount` >0';
        $data = $this->db->select($sql);
        return $data;
    }

    private function getImageUrl($id, $type = 1)
    {
        $images = CustomManager::getImages($id, $type);
        if ($images) {
            return FileHelper::getFileUrl($images[0]['ci_img']);
        }

        return '';
    }
}
