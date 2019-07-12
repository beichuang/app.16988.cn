<?php

/**
 * Description of EventPost.
 *
 */

namespace Lib\Common;

class LogManager
{
    public static $logType = [
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
     * @param type var Description
     *
     * @return return type
     */
    public static function log($objectId, $type, $info, $appId)
    {
        $logInfo['mal_objectId'] = $objectId;
        $logInfo['mal_type'] = $type;
        $logInfo['mal_name'] = self::$logType[$type];
        $logInfo['mal_desc'] = json_encode($info);
        $logInfo['mal_createDate'] = date('Y-m-d H:i:s');
        $logInfo['app_id'] = $appId;
        $logInfo['mal_ip'] = $_SERVER['REMOTE_ADDR'];
        $logInfo['mal_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $result = app('mysqlbxd_mall_common')->insert('mall_action_log', $logInfo);
        if (!empty($result[1])) {
            return true;
        } else {
            return false;
        }
    }
}
