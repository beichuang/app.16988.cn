<?php
namespace Model\Message;

use Lib\Base\BaseModel;

class DialogContent extends BaseModel
{

    protected $table = 'message_dialog_content';

    protected $id = 'msgdc_id';

    /**
     * 新增对话内容
     *
     * @param int $msgd_id            
     * @param int $u_id            
     * @param int $msgdc_receiveUserId            
     * @param string $msgdc_content            
     * @param string $msgdc_userAgent            
     * @param int $msgdc_type            
     * @param number $msgdc_isSenderRemoved            
     * @param number $msgdc_isReceiverRemoved            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($msgd_id, $u_id, $msgdc_receiveUserId, $msgdc_content, $msgdc_userAgent, $msgdc_type, 
        $msgdc_isSenderRemoved = 0, $msgdc_isReceiverRemoved = 0)
    {
        if (! $msgd_id || ! $u_id || ! $msgdc_receiveUserId || ! isset($msgdc_content) || ! isset($msgdc_type)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'u_id' => $u_id,
            'msgd_id' => $msgd_id,
            'msgdc_content' => $msgdc_content,
            'msgdc_userAgent' => $msgdc_userAgent,
            'msgdc_receiveUserId' => $msgdc_receiveUserId,
            'msgdc_type' => $msgdc_type,
            'msgdc_isSenderRemoved' => $msgdc_isSenderRemoved,
            'msgdc_isReceiverRemoved' => $msgdc_isReceiverRemoved,
            'msgdc_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        return [
            $data,
            $count
        ];
    }

    /**
     * 发送者移除对话内容
     *
     * @param int $uid            
     * @param int $msgdc_id            
     * @throws \Exception\ModelException
     * @return number
     */
    public function removeDialogContentByUid($uid, $msgdc_id)
    {
        $row = $this->oneById($msgdc_id);
        if (! $row || $row['u_id'] != $uid) {
            throw new \Exception\ServiceException("已移除");
        }
        return $this->update($msgdc_id, array(
            'msgdc_isSenderRemoved' => 1
        ));
    }

    /**
     * 接受者移除对话内容
     *
     * @param int $uid            
     * @param int $msgdc_id            
     * @throws \Exception\ModelException
     * @return number
     */
    public function removeDialogContentByReceiverUid($uid, $msgdc_id)
    {
        $row = $this->oneById($msgdc_id);
        if (! $row || $row['msgdc_receiveUserId'] != $uid) {
            throw new \Exception\ServiceException("已移除");
        }
        return $this->update($msgdc_id, array(
            'msgdc_isReceiverRemoved' => 1
        ));
    }

    /**
     * 查询搜索列表
     *
     * @param array $params            
     * @param int $page            
     * @param int $pagesize            
     * @return array $List
     */
    public function lists($params, $page, $pagesize)
    {
        $whereArr = $bindData = [];
        if (isset($params['msgd_id']) && $params['msgd_id'] != '') {
            $whereArr[] = 'dialogContent.msgd_id = :msgd_id';
            $bindData[':msgd_id'] = $params['msgd_id'];
        }
        if (isset($params['msgdc_id_before']) && $params['msgdc_id_before'] != '') {
            $whereArr[] = '`dialogContent`.msgdc_id < :msgdc_id_before';
            $bindData[':msgdc_id_before'] = $params['msgdc_id_before'];
        }
        if (isset($params['msgdc_id_after']) && $params['msgdc_id_after'] != '') {
            $whereArr[] = '`dialogContent`.msgdc_id > :msgdc_id_after';
            $bindData[':msgdc_id_after'] = $params['msgdc_id_after'];
        }
        // if (isset($params['u_id']) && $params['u_id'] != '' && isset($params['msgdc_receiveUserId']) &&
        // $params['msgdc_receiveUserId'] != '') {
        // $whereArr[] = ' ((dialogContent.u_id = :u_id1 and `dialogContent`.msgdc_receiveUserId = :msgdc_receiveUserId1 ) or (dialogContent.u_id = :u_id2 and `dialogContent`.msgdc_receiveUserId = :msgdc_receiveUserId2 ))';
        // $bindData[':u_id1'] = $params['u_id'];
        // $bindData[':msgdc_receiveUserId1'] = $params['msgdc_receiveUserId'];
        // $bindData[':u_id2'] = $params['msgdc_receiveUserId'];
        // $bindData[':msgdc_receiveUserId2'] = $params['u_id'];
        // } elseif (isset($params['u_id']) && $params['u_id'] != '') {
        // $whereArr[] = 'dialogContent.u_id = :u_id';
        // $bindData[':u_id'] = $params['u_id'];
        // } elseif (isset($params['msgdc_receiveUserId']) && $params['msgdc_receiveUserId'] != '') {
        // $whereArr[] = '`dialogContent`.msgdc_receiveUserId = :msgdc_receiveUserId';
        // $bindData[':msgdc_receiveUserId'] = $params['msgdc_receiveUserId'];
        // }
        if (isset($params['createDateStart']) && $params['createDateStart'] != '') {
            $whereArr[] = '`dialogContent`.msgdc_createDate >= :createDateStart';
            $bindData[':createDateStart'] = $params['createDateStart'];
        }
        if (isset($params['createDateEnd']) && $params['createDateEnd'] != '') {
            $whereArr[] = '`dialogContent`.msgdc_createDate <= :createDateEnd';
            $bindData[':createDateEnd'] = $params['createDateEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT dialogContent.* FROM `{$this->table}` dialogContent
                $where ORDER BY dialogContent.msgdc_id DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` dialogContent $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
