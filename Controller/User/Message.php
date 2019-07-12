<?php
/**
 * 好友会话消息
 * @author Administrator
 *
 */
namespace Controller\User;

use Framework\Helper\SessionHelper;
use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Framework\Lib\Validation;
use Lib\Common\SessionKeys;
use Exception\ModelException;

class Message extends BaseController
{

    private $messageMode = null;

    public function __construct()
    {
        parent::__construct();
        $this->messageMode = new \Model\Message\DialogContent();
    }

    /**
     * 上传图片
     */
    public function uploadImages()
    {
        $types = [
            'image/jpeg' => "jpg",
            'image/gif' => 'gif',
            'image/png' => 'png',
            'image/x-png' => 'png'
        ];
        $size = 2 * 1024 * 1024;
        $ftpConfigKey = 'user_message_picture';
        $filesData = FileHelper::uploadFiles($ftpConfigKey, $size, $types);
        if ($filesData) {
            if (empty($filesData['result'])) {
                $this->responseJSON(empty($filesData['data']) ? [] : $filesData['data'], 1, 1,
                    empty($filesData['message']) ? '' : $filesData['message']);
            } else {
                $this->responseJSON($filesData['data']);
            }
        } else {
            $this->responseJSON([], 1, 1, '上传文件时发生异常');
        }
    }

    /**
     * 新增消息
     *
     * @throws ModelException
     */
    public function create()
    {
        $u_id = $this->uid;
        $msgd_userIds = array();
        $msgd_userIdsStr = app('request')->params('msgd_userIds', '');
        $msgdc_content = app('request')->params('msgdc_content', '');
        $msgd_id = app('request')->params('msgd_id', '');
        $msgdc_type = app('request')->params('msgdc_type', '0');
        if (! $msgd_userIdsStr) {
            throw new ParamsInvalidException('消息接收人不能为空');
        }
        $msgd_userIds = explode(',', $msgd_userIdsStr);
        foreach ($msgd_userIds as $i => $tmpUid) {
            if (! $tmpUid) {
                unset($msgd_userIds[$i]);
            }
            if (! is_numeric($tmpUid)) {
                throw new ParamsInvalidException("用户id格式错误");
            }
        }
        if (empty($msgd_userIds)) {
            throw new ParamsInvalidException('消息接收人不能为空');
        }
        $msgdc_content = $this->parseMessageContent($msgdc_type, $msgdc_content);
        
        array_push($msgd_userIds, $u_id);
        // 处理会话
        $modelDialog = new \Model\Message\Dialog();
        if ($msgd_id) {
            if (! $modelDialog->queryMsgdId($msgd_id)) {
                throw new ModelException('不存在的会话id');
            }
        } else {
            $msgd_id = $modelDialog->save($u_id, $msgd_userIds);
        }
        // 处理会话消息
        $msgLib = new \Lib\User\Message();
        if (! $msgd_id) {
            throw new ModelException('保存会话失败');
        }
        $userInfo = SessionHelper::get(SessionKeys::USER_INFO);
        $data = $msgLib->sendChatMessage($msgd_id, $u_id, $msgdc_content, $msgdc_type, $msgd_userIds, $userInfo);
        $this->responseJSON($data);
    }

    /**
     * 会话消息列表
     *
     * @throws ModelException
     */
    public function lists()
    {
        $u_id = $this->uid;
        $msgd_id = app('request')->params('msgd_id', '');
        $msgdc_id_before = app('request')->params('msgdc_id_before', '');
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 10);
        if (! $msgd_id || ! is_numeric($msgd_id)) {
            $msgd_id = false;
            $msgdc_receiveUserId = app('request')->params('msgdc_receiveUserId');
            if (! $msgdc_receiveUserId) {
                throw new ParamsInvalidException('对方用户id必须或格式错误');
            }
            $dialogModel = new \Model\Message\Dialog();
            $msgd_id = $dialogModel->queryMsgdIdByUids(
                [
                    $u_id,
                    $msgdc_receiveUserId
                ]);
        }
        $rows = [];
        if ($msgd_id) {
            $param['u_id'] = $u_id;
            $param['msgd_id'] = $msgd_id;
            if ($msgdc_id_before) {
                $param['msgdc_id_before'] = $msgdc_id_before;
            }
            list ($rows, $count) = $this->messageMode->lists($param, $page, $pageSize);
            if ($rows && is_array($rows) && ! empty($rows)) {
                $users = [];
                foreach ($rows as &$row) {
                    $users[$row['u_id']] = [
                        'u_id' => $row['u_id']
                    ];
                    $users[$row['msgdc_receiveUserId']] = [
                        'u_id' => $row['msgdc_receiveUserId']
                    ];
                }
                $userLib = new \Lib\User\User();
                $userLib->extendUserInfos2Array($users, 'u_id', 
                    array(
                        'u_nickname' => 'nickname',
                        'u_avatar' => 'avatar'
                    ));
                foreach ($rows as &$v) {
                    $time = strtotime($v['msgdc_time']);
                    $v['displayTime'] = date_format_to_display($time);
                    $v = array_merge($v, $users[$v['u_id']]);
                }
                // app追加聊天记录方向是向上
                sort($rows);
            }
        }
        $this->responseJSON($rows);
    }

    /**
     * 格式化消息内容
     */
    private function parseMessageContent($msgdc_type, $msgdc_content)
    {
        switch ($msgdc_type) {
            case 0:
                $msgdc_content = $this->handleData($msgdc_content, 'str');
                if (empty($msgdc_content)) {
                    throw new ParamsInvalidException('消息内容不能为空');
                }
                break;
            case 1:
                $msgdc_contentArr = json_decode($msgdc_content,true);
                if (json_last_error()) {
                    throw new ParamsInvalidException("消息数据格式错误");
                }
                if (! $msgdc_contentArr || ! is_array($msgdc_contentArr) || ! isset($msgdc_contentArr['type']) ||
                     ! isset($msgdc_contentArr['filePath']) || ! isset($msgdc_contentArr['size']) ||
                     ! isset($msgdc_contentArr['previewUrl']) || ! isset($msgdc_contentArr['imageSize']['width']) ||
                     ! isset($msgdc_contentArr['imageSize']['height'])) {
                    throw new ParamsInvalidException("消息格式错误");
                }
                $msgdc_content = json_encode(
                    [
                        'type' => $msgdc_contentArr['type'],
                        'filePath' => $msgdc_contentArr['filePath'],
                        'size' => $msgdc_contentArr['size'],
                        'previewUrl' => $msgdc_contentArr['previewUrl'],
                        'imageSize' => [
                            'width' => $msgdc_contentArr['imageSize']['width'],
                            'height' => $msgdc_contentArr['imageSize']['height']
                        ]
                    ]);
                break;
        }
        return $msgdc_content;
    }

    /**
     * 对数据进行初级过滤
     *
     * @param string $data
     *            要处理的数据
     * @param string $filter
     *            过滤的方式
     * @return mix
     */
    private function handleData($data = '', $filter = '')
    {
        switch ($filter) {
            case 'int':
                return abs(intval($data));
                break;
            
            case 'str':
                return trim(htmlspecialchars(strip_tags($data)));
                break;
            
            case 'float':
                return floatval($data);
                break;
            
            case 'arr':
                return (array) $data;
                break;
        }
        
        return '';
    }
}
