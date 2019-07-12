<?php
namespace Lib\User;

use Exception\InternalException;
use Exception\ServiceException;
use Framework\Lib\Validation;
use Exception\ParamsInvalidException;

/**
 * 消息公共类
 *
 * @author Administrator
 *        
 */
class Message
{

    private $userApi = null;

    public function __construct()
    {
        $this->userApi = get_api_client('User');
        $this->userApi->setHeader('X-Forwarded-Proto', get_request_url_schema());
    }

    /**
     * 发送聊天消息信息
     * @author: 
     * @dateTime  2017-04-19T10:17:14+0800
     * @copyright [copyright]
     * @license   [license]
     * @version   [version]
     * @param     [type]                   $msgd_id       [会话id]
     * @param     [type]                   $u_id          [用户id]
     * @param     [type]                   $msgdc_content [会话内容]
     * @param     [type]                   $msgdc_type    [会话类型]
     * @param     [type]                   $msgd_userIds  [消息对象id]
     * @param     [type]                   $userInfo      [用户详情]
     * @return    [type]                                  [description]
     */
    public function sendChatMessage($msgd_id,$u_id, $msgdc_content,$msgdc_type,$msgd_userIds, $userInfo)
    {
        $messageModel = new \Model\Message\DialogContent();
        if (isset($msgd_id) && isset($msgdc_content) && isset($msgd_userIds) && isset($msgdc_type)) {
            $msgd_id = intval($msgd_id);
            array_pop($msgd_userIds);
            $msgdc_userAgent = '好友消息';
            foreach ($msgd_userIds as $k => $v) {
                list ($data, $count) = $messageModel->add($msgd_id, $u_id, $v, $msgdc_content, $msgdc_userAgent,$msgdc_type);
            }
            $data['displayTime'] = date_format_to_display(time());
            if ($count && $data) {
                $data['nickname'] = $userInfo['u_nickname'];
                $data['avatar'] = $userInfo['u_avatar'];
                $uid = implode(',', $msgd_userIds);
                $previewContent = "$msgdc_content";
                $title = "{$data['nickname']}给您发来一条消息";
                $this->pushFriendsDialogMessage($uid, $data, $previewContent, $title);
            }
            return $data;
        }else {
            throw new ServiceException("消息发送失败");
        }
    }
    /**
     * 推送消息
     * @author: 
     * @dateTime  2017-04-19T10:50:53+0800
     * @copyright [copyright]
     * @license   [license]
     * @version   [version]
     * @param     [type]                   $uids           [消息id]
     * @param     [type]                   $content        [消息内容]
     * @param     [type]                   $previewContent [description]
     * @param     [type]                   $title          [标题]
     * @return    [type]                                   [description]
     */
    public function pushFriendsDialogMessage($uids, $content, $previewContent, $title)
    {
        $type = \Lib\Common\AppMessagePush::PUSH_TYPE_FRIENDS_DIALOG_MESSAGE;
        $res = \Lib\Common\AppMessagePush::push($uids, $title, $previewContent, $content, $type);
        return $res;
    }

}
