<?php
namespace Model\Message;

use Lib\Base\BaseModel;

class Notice extends BaseModel
{

    protected $table = 'message_notice';

    protected $id = 'msgn_id';

    /**
     * 新增通知
     *
     * @param int $u_id            
     * @param string $msgn_content            
     * @param string $msgn_title            
     * @param number $msgn_type            
     * @param number $msgn_isRead            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($u_id, $msgn_content, $msgn_title = '', $msgn_isRead = 0, $msgn_type = 0)
    {
        if (! $u_id || ! $msgn_content) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (strlen($msgn_title) > 200) {
            throw new \Exception\ParamsInvalidException("标题过长");
        }
        $data = array(
            'msgn_type' => $msgn_type,
            'u_id' => $u_id,
            'msgn_content' => $msgn_content,
            'msgn_title' => $msgn_title,
            'msgn_time' => date('Y-m-d H:i:s'),
            'msgn_isRead' => $msgn_isRead
        );
        list ($count, $id) = $this->insert($data);
        return $id;
    }

    /**
     * 标记通知已读
     *
     * @param int $u_id            
     * @param int $msgn_id            
     * @throws \Exception\ModelException
     * @return number
     */
    public function noticeRead($u_id, $msgn_id)
    {
        $row = $this->oneById($msgn_id);
        if (! $row || $row['u_id'] != $u_id) {
            throw new \Exception\ServiceException("通知失效");
        }
        if ($row['msgn_isRead'] == 1) {
            throw new \Exception\ServiceException("已标记");
        }
        return $this->update($msgn_id, array(
            'msgn_isRead' => 1
        ));
    }

    /**
     * 移除通知
     *
     * @param int $u_id            
     * @param int $msgn_id            
     * @throws \Exception\ModelException
     * @return Ambigous <number, \Framework\Route, \Framework\Route>
     */
    public function removeNotice($u_id, $msgn_id)
    {
        $row = $this->oneById($msgn_id);
        if (! $row || $row['u_id'] != $u_id) {
            throw new \Exception\ServiceException("已移除");
        }
        $id = $row['msgn_id'];
        return $this->delete($id);
    }

    /**
     * 更新数据
     * 
     * @param int $id
     *            id
     * @param array $data
     *            更新数据
     * @return int 受影响的行数
     */
    public function setRead($u_id, $params)
    {
        $row = $this->update($u_id, $params);
        return $row;
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
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'msgn.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['msgn_type']) && $params['msgn_type'] != '') {
            $whereArr[] = 'msgn.msgn_type = :msgn_type';
            $bindData[':msgn_type'] = $params['msgn_type'];
        }
        if (isset($params['msgn_isRead']) && $params['msgn_isRead'] != '') {
            $whereArr[] = 'msgn.msgn_isRead = :msgn_isRead';
            $bindData[':msgn_isRead'] = $params['msgn_isRead'];
        }
        
        if (isset($params['noticeTimeStart']) && $params['noticeTimeStart'] != '') {
            $whereArr[] = '`msgn`.msgn_time >= :noticeTimeStart';
            $bindData[':noticeTimeStart'] = $params['noticeTimeStart'];
        }
        
        if (isset($params['noticeTimeEnd']) && $params['noticeTimeEnd'] != '') {
            $whereArr[] = '`msgn`.msgn_time <= :noticeTimeEnd';
            $bindData[':noticeTimeEnd'] = $params['noticeTimeEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT msgn.* FROM `{$this->table}` msgn
                $where ORDER BY msgn.msgn_id DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` msgn $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
