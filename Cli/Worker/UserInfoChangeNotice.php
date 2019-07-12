<?php
namespace Cli\Worker;

use Lib\Common\AppMessagePush;

class UserInfoChangeNotice
{

    private $mainQueuePrefix = null;

    private $queueFrequency = null;

    private $queueFlag = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->mainQueuePrefix = config('data_change_notice_queue.interface.user.redis_queue_prefix');
        $this->queueFlag = config('data_change_notice_queue.interface.user.mainQueueFlag');
        $this->queueFrequency = config('data_change_notice_queue.interface.user.frequency', 200000);
    }

    /**
     * 入口函数
     */
    public function run()
    {
        $restartStart = config('data_change_notice_queue.interface.user.workrRestart.0');
        $restartEnd = config('data_change_notice_queue.interface.user.workrRestart.1');
        while (true) {
            if ($message = $this->popQueue()) {
                try {
                    $uid = $message['uid'];
                    $changeType = $message['changeType'];
                    $messageTitle = $message['messageTitle'];
                    $messageContent = $message['messageContent'];
                    $messageParams = $message['messageParams'];
                    $this->appMessagePush($uid, $changeType, $messageTitle, $messageContent, $messageParams);
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

    private function appMessagePush($uid, $changeType, $messageTitle, $messageContent,$messageParams)
    {
        if (empty($uid) || !isset($changeType) || empty($messageTitle) || empty($messageContent)) {
            return false;
        }

        $previewContent = empty($messageParams['previewContent']) ? $messageContent : $messageParams['previewContent'];
        switch ($changeType) {
            case  22:
                $messageParams['type'] = 'cert';
                break;
            default:
                break;
        }

        AppMessagePush::push($uid, $messageTitle, $previewContent, $messageParams, AppMessagePush::PUSH_TYPE_CERTITICATION_STATUS_CHANGE);
    }
}
