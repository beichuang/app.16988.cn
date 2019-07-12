<?php
namespace Cli\Worker;

use Lib\Common\AppMessagePush;

class GoodsStatusChangeNotice
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
        $this->queueFlag = config('data_change_notice_queue.interface.goods.appMessagePushQueue');
        $this->queueFrequency = config('data_change_notice_queue.interface.goods.frequency', 200000);
    }

    /**
     * 入口函数
     */
    public function run()
    {
        $restartStart = config('data_change_notice_queue.interface.goods.workrRestart.0');
        $restartEnd = config('data_change_notice_queue.interface.goods.workrRestart.1');
        while (true) {
            if ($message = $this->popQueue()) {
                try {
                    var_dump($message);
                    $changeType = $message['changeType'];
                    $g_id = $message['g_id'];
                    $goodsInfo = $message['goodsInfo'];
                    $x_forwoarded_url = isset($message['x_forwoarded_url']) ? $message['x_forwoarded_url'] : '';
                    $this->appMessagePush($changeType, $g_id, $goodsInfo, $x_forwoarded_url);
                } catch (\Exception $e) {
                    wlog_exception($e);
                }
            }
            exitTask($restartStart, $restartEnd);
            sleep(180);
        }
    }

    /**
     * 取队列
     *
     * @return mixed boolean
     */
    private function popQueue()
    {
        $raw = app('redis')->rPop($this->mainQueuePrefix . $this->queueFlag);
        if ($raw) {
            $message = unserialize($raw);
            if (is_array($message) && isset($message['changeType']) && isset($message['g_id'])) {
                return $message;
            }
        }
        return false;
    }

    /**
     * 推送APP消息
     *
     * @param unknown $changeType
     * @param unknown $g_id
     * @param unknown $goodsInfo
     * @return boolean
     */
    private function appMessagePush($changeType, $g_id, $goodsInfo, $x_forwoarded_url)
    {
        if (! isset($changeType) || ! $g_id || ! $goodsInfo || ! is_array($goodsInfo) || empty($goodsInfo)) {
            return false;
        }
        $g_name = $goodsInfo['g_name'];
        $g_salesId = $goodsInfo['g_salesId'];
        $g_updateDate = $goodsInfo['g_updateDate'];
        $g_status = $goodsInfo['g_status'];
        $uids=$goodsInfo['g_salesId'];
        $time=$goodsInfo['g_updateDate'];
        $messageTitle = '';
        $messageContent = '';
        $userLib = new \Lib\User\User(false);
        $info = array();
        switch ($changeType) {
            case 6:
                $messageTitle = '商品审核通知';
                $messageContent = "您发布的商品《{$g_name}》审核通过，已经上架";
                break;

            case 70:
                $trade = new \Model\Pay\Trade();
                $config = load_row_configs_trim_prefix('api.Mall');

                $tradeId = $config['appId'].'-'.$goodsInfo['o_sn'];
                $tradeInfo = $trade->getTrade($tradeId);

                $amount = $tradeInfo['tr_amount']/100;

                $buyerUid = $tradeInfo['tr_uid'];
                $userInfos = $userLib->getUserInfo([$buyerUid]);
                $username = $userInfos[$buyerUid]['u_realname'] ? $userInfos[$buyerUid]['u_realname'] : $userInfos[$buyerUid]['u_nickname'];

                if ( isset($goodsInfo['uid']) ) {
                    $uinfo = $userLib->getUserInfo([$goodsInfo['uid']]);
                    $info['u_avatar'] = $uinfo[ $goodsInfo['uid'] ]['u_avatar'];
                }
                $messageTitle = $username;
                $messageContent = "您的作品：《{$g_name}》 收到了来自 {$username} 的打赏 {$amount}元，您可以在钱包中查看";
                $time = date('Y-m-d H:i:s');
                break;

        }
        if ($messageContent && $uids) {
            if (! $x_forwoarded_url) {
                $x_forwoarded_url = get_request_url_schema() . '://' . config('app.baseDomain');
            }
            $url = $x_forwoarded_url . "/html/details.html?id={$g_id}";

            $info['sid'] = $g_id;
            AppMessagePush::push($uids, $messageTitle, "{$messageContent}，{$time}", $info,
                AppMessagePush::PUSH_TYPE_GOODS_STATUS_CHANGE);
        }
    }
}
