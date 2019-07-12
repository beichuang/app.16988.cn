<?php
/**
 * 系统消息
 * @author Administrator
 *
 */
namespace Controller\User;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Framework\Lib\Validation;
use Lib\Common\SessionKeys;
use Exception\ModelException;

class Notice extends BaseController
{

    private $noticeMode = null;

    public function __construct()
    {
        parent::__construct();
        $this->noticeMode = new \Model\Message\Notice();
    }

    /**
     * 新增消息
     *
     * @throws ModelException
     */
    public function addNotic()
    {
        $u_id = $this->uid;
        $msgn_content = app('request')->params('msgn_content');
        $msgn_title = app('request')->params('msgn_title');
        $msgn_isRead = app('request')->params('msgn_isRead');
        isset($msgn_isRead) ? $this->handleData($msgn_isRead, 'int') : 0;
        isset($msgn_title) ? $this->handleData($msgn_title, 'str') : " ";
        $msgn_content = $this->handleData($msgn_content, 'str');
        if (empty($msgn_content)) {
            throw new ParamsInvalidException("内容不能为空");
        }
        $rest = $this->noticeMode->add($u_id, $msgn_content, $msgn_title, $msgn_isRead);
        if (! $rest) {
            throw new ModelException('添加失败');
        }
        $this->responseJSON(true);
    }

    /**
     * 系统消息列表
     *
     * @throws ModelException
     */
    public function lists()
    {
        $u_id = $this->uid;
        // $msgn_content = app('request')->params('msgn_content');
        $page = 1;
        $pagesize = 10;
        $param['u_id'] = $u_id;
        list ($rows, $count) = $this->noticeMode->lists($param, $page, $pagesize);
        if ($rows && is_array($rows)) {
            foreach ($rows as &$row) {
                $row['displayTime'] = date_format_to_display(strtotime($row['msgn_time']));
            }
        }
        $this->responseJSON($rows);
    }

    /**
     * 删除系统消息列表
     *
     * @throws ModelException
     */
    public function delete()
    {
        $u_id = $this->uid;
        $msgn_id = app('request')->params('msgn_id');
        $this->handleData($msgn_id, 'int');
        if (! $msgn_id) {
            throw new ParamsInvalidException("id不能为空");
        }
        $rest = $this->noticeMode->removeNotice($u_id, $msgn_id);
        if (! $rest) {
            throw new ModelException('删除失败');
        }
        $this->responseJSON(true);
    }

    /**
     * 标记消息已读
     *
     * @throws ModelException
     */
    public function setRead()
    {
        $u_id = $this->uid;
        $msgn_id = app('request')->params('msgn_id');
        $this->handleData($msgn_id, 'int');
        if (! $msgn_id) {
            throw new ParamsInvalidException("id不能为空");
        }
        $msgn_isRead['msgn_isRead'] = 1;
        $rest = $this->noticeMode->update($msgn_id, $msgn_isRead);
        if (! $rest) {
            throw new ModelException('更新失败');
        }
        $this->responseJSON(true);
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
