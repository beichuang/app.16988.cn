<?php

/**
 * Description of EventPost.
 *
 */
namespace Lib\Common;

use Framework\Helper\SessionHelper;

class QueueManager
{
    public static $config = array();

    public static $queueType = [
        // 商品评论
        1 => '评论',  // 商品评论
        2 => '回复',  // 商品评论回复
        // 艺术圈
        6 => '点赞',
        7 => '评论',
        8 => '回复',
    ];

    /**
     * undocumented function summary.
     *
     * Undocumented function long description
     *
     * @param
     *            type var Description
     *
     * @return return type
     */
    public static function queue($id, $type, $info, $appId)
    {
        if (self::getConfig('stopMainQueue')) {
            return true;
        }
        switch ($type) {
            case 1:
            case 2:
                self::dealQueueGoodsDiscuss($id, $type, $info, $appId);
                break;
            case 6:
            case 7:
            case 8:
                self::dealQueueTreasureDiscuss($id, $type, $info, $appId);
                break;
        }
    }

    /**
     * 商品评论回复
     */
    public static function dealQueueGoodsDiscuss($id, $type, $info, $appId)
    {
        $typeKey = 'GoodsDiscussAppMessagePushQueue';
        $userInfo = SessionHelper::get(SessionKeys::USER_INFO);
        $userName = !empty($userInfo['u_realname']) ? $userInfo['u_realname'] : $userInfo['u_nickname'];
        $data = array(
                'u_realname' => $userInfo['u_realname'],
                'u_nickname' => $userInfo['u_nickname'],
                'id' => $id,
            );
        $data['u_avatar'] = isset($userInfo['u_avatar']) && $userInfo['u_avatar'] ? $userInfo['u_avatar'] : '';

        $goodsCommentModel = new \Model\Mall\GoodsComment();
        // 评论
        if ( empty($info) || !is_array($info) ) {
            $info = $goodsCommentModel->oneById($id);
        }
        $data['sid'] = $info['g_id'];

        // 获取商品的所属用户
        $goodsLib = new \Lib\Mall\Goods();
        $goodsRet = $goodsLib->itemQuery(array('id'=>$info['g_id']));
        if ( !isset($goodsRet['list']) || empty($goodsRet['list']) ) {
            return;
        }
        $goodsInfo = current($goodsRet['list']);

        // 添加商品图片
        $data['image'] = isset($goodsInfo['image'][0]['gi_img']) ? $goodsInfo['image'][0]['gi_img'] : '';

        $objectId = $goodsInfo['g_salesId'];
        $userInfo = SessionHelper::get(SessionKeys::USER_INFO);
        $title = $userName;
        $content = $userName." 评论了您的商品: ".$info['gc_content'];
        self::pushQueueDiscuss($objectId, $type, $title, $content, $data, $appId, $typeKey);
        if ( $type == 2 && $info['gc_pid'] ) {
            $goodsCommentInfo = $goodsCommentModel->oneById($info['gc_pid']);
            $objectId = $goodsCommentInfo['u_id'];
            $title = $userName;
            $content = $userName . " 评论了您的评论: " . $info['gc_content'];
            self::pushQueueDiscuss($objectId, $type, $title, $content, $data, $appId, $typeKey);
        }
    }

    /**
     * 艺术圈评论
     */
    public static function dealQueueTreasureDiscuss($id, $type, $info, $appId)
    {
        $typeKey = 'TreasureDiscussAppMessagePushQueue';
        $userInfo = SessionHelper::get(SessionKeys::USER_INFO);
        $userName = $userInfo['u_nickname'];
        $data = array(
                'u_realname' => $userInfo['u_realname'],
                'u_nickname' => $userInfo['u_nickname'],
                'id' => $id,
            );
        $data['u_avatar'] = isset($userInfo['u_avatar']) && $userInfo['u_avatar'] ? $userInfo['u_avatar'] : '';

        if ( $type == 6 ) {
            $treaLikeModel = new \Model\Treasure\TreasureLikeLog();
            $info = $treaLikeModel->oneById($id);

            $data['sid'] = $info['t_id'];

            $treasureModel = new \Model\Treasure\Treasure();
            $treasureInfo = $treasureModel->oneById($info['t_id']);

            $treasureImgModel = new \Model\Treasure\TreasureImage();
            list ($pic, $picTotalCount) = $treasureImgModel->lists(array('t_id' => $info['t_id']), 1, 10);
            $data['image'] = isset($pic[0]['ti_img']) ? $pic[0]['ti_img'] : '';

            $objectId = $treasureInfo['u_id'];
            $title = $userName;
            $content = $userName . " 点赞了您的艺术圈";
            return self::pushQueueDiscuss($objectId, $type, $title, $content, $data, $appId, $typeKey);
        }

        if ( in_array($type, [7, 8]) ) {
            $data['sid'] = $info['t_id'];
            // 发说说对应用户id
            $treasureModel = new \Model\Treasure\Treasure();
            $treasureInfo = $treasureModel->oneById($info['t_id']);

            $treasureImgModel = new \Model\Treasure\TreasureImage();
            list ($pic, $picTotalCount) = $treasureImgModel->lists(array('t_id' => $info['t_id']), 1, 10);
            $data['image'] = isset($pic[0]['ti_img']) ? $pic[0]['ti_img'] : '';

            $objectId = $treasureInfo['u_id'];
            $title = $userName;
            $content = $userName . " 评论了您: ".$info['tc_content'];
            self::pushQueueDiscuss($objectId, $type, $title, $content, $data, $appId, $typeKey);
            if ( $type == 8 && $info['tc_pid'] ) {// 回复 父评论id+说说对应用户id
                $treasureCommentModel = new \Model\Treasure\TreasureComment();
                $treasureCommentInfo = $treasureCommentModel->oneById($info['tc_pid']);
                $objectId = $treasureCommentInfo['u_id'];
                $title = $userName;
                $content = $userName . '回复了您:' . $info['tc_content'];
                return self::pushQueueDiscuss($objectId, $type, $title, $content, $data, $appId, $typeKey);
            }
        }
    }

    private static function pushQueueDiscuss($objectId, $type, $title, $content, $data, $appId, $typeKey)
    {
        $message = array(
            'uid' => $objectId,
            'changeType' => $type,
            'title' => $title,
            'content' => $content,
            'info' => $data,
            'appId' => $appId,
            'x_forwoarded_url'=>'',//get_request_x_forwoarded_url()
        );
        $queueName = self::getConfig($typeKey);
        return self::pushToQueue($message,$queueName);
    }

    /**
     * 推送到队列
     * @param unknown $message
     * @return boolean
     */
    private static function pushToQueue($message,$queueName)
    {
        $redis_queue_prefix = self::getConfig('redis_queue_prefix');
        wlog([$redis_queue_prefix . $queueName, $message],'push-queue');
        if (! app('redis')->lPush($redis_queue_prefix . $queueName, serialize($message))) {
            wlog($message, "ERROR_" . __CLASS__ . "放队列失败！", \Framework\Log::ERROR);
        }
        return true;
    }

    /**
     * 获取配置
     *
     * @param unknown $key
     * @return multitype: NULL
     */
    public static function getConfig($key)
    {
        if (empty(self::$config)) {
            self::initConfig();
        }
        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }
        return null;
    }

    /**
     * 初始化配置
     */
    public static function initConfig()
    {
        self::$config['redis_queue_prefix'] = config('data_change_notice_queue.redis_queue_prefix',
            'api_user_queue_User_Data_Change_Notice_Key_');

        self::$config['mainQueueFlag'] = config('data_change_notice_queue.mainQueueFlag');
        self::$config['stopMainQueue'] = config('data_change_notice_queue.stopMainQueue');

        self::$config['GoodsDiscussAppMessagePushQueue'] = config(
            'data_change_notice_queue.interface.goodsDiscuss.appMessagePushQueue');
        self::$config['TreasureDiscussAppMessagePushQueue'] = config(
            'data_change_notice_queue.interface.treasureDiscuss.appMessagePushQueue');
    }
}
