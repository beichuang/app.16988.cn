<?php
namespace Cli\Worker;

use Lib\Common\AppMessagePush;

class GoodsDiscussNotice
{

    private $mainQueuePrefix = null;

    private $queueFrequency = null;

    private $queueFlag = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->mainQueuePrefix = config('data_change_notice_queue.redis_queue_prefix', 
            'api_user_queue_User_Data_Change_Notice_Key_');
        $this->queueFlag = config('data_change_notice_queue.interface.goodsDiscuss.appMessagePushQueue');
        $this->queueFrequency = config('data_change_notice_queue.interface.goodsDiscuss.frequency', 200000);
    }

    /**
     * 入口函数
     */
    public function run()
    {
        $restartStart = config('data_change_notice_queue.interface.goodsDiscuss.workrRestart.0');
        $restartEnd = config('data_change_notice_queue.interface.goodsDiscuss.workrRestart.1');
        while (true) {
            if ($message = $this->popQueue()) {
                try {
                    var_dump($message);
                    $this->appMessagePush($message);
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
    private function popQueue()
    {
        $raw = app('redis')->rPop($this->mainQueuePrefix . $this->queueFlag);
        if ($raw) {
            $message = unserialize($raw);
            if (is_array($message) && isset($message['changeType']) && isset($message['uid'])) {
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
    private function appMessagePush($message)
    {
        if ( !isset($message['changeType']) || empty($message['changeType']) || 
            !isset($message['uid']) || empty($message['uid']) ) {
            return false;
        }

        $msgType = AppMessagePush::PUSH_TYPE_GOODS_DISCUSS;
        $uid = $message['uid'];
        $title = $message['title'];
        $content = $message['content'];
        $info = $message['info'];

        $ret = AppMessagePush::push($uid, $title, $content, $info, $msgType, date('Y-m-d H:i:s'));
        var_dump('push success');
    }
}
