<?php
namespace Model\Message;

use Lib\Base\BaseModel;

class Dialog extends BaseModel
{

    protected $table = 'message_dialog';

    protected $id = 'msgd_id';

    /**
     * 新增/更新对话
     *
     * @param int $u_id            
     * @param array $u_ids            
     * @param string $msgd_title            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function save($u_id, $u_ids, $msgd_title = '')
    {
        if (! $u_id || ! is_array($u_ids)) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (strlen($msgd_title) > 50) {
            throw new \Exception\ParamsInvalidException("标题过长");
        }
        $filterUids = array();
        foreach ($u_ids as $tmpId) {
            if ($tmpId) {
                $filterUids[$tmpId] = $tmpId;
            }
        }
        
        if (count($filterUids) < 2) {
            throw new \Exception\ServiceException("会话至少要两个人");
        }
        if ($id = $this->queryMsgdIdByUids($u_ids)) {
            $this->update($id, array(
                'msgd_updateDate' => date('Y-m-d H:i:s')
            ));
            return $id;
        }
        sort($filterUids);
        $data = array(
            'msgd_title' => $msgd_title,
            'u_id' => $u_id,
            'msgd_userIds' => implode(',', $filterUids),
            'msgd_createDate' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        //保存用户会话对应关系
        $dialogUserModel = new DialogUser();
        foreach ($filterUids as $tmpUid) {
            $dialogUserModel->save($id, $tmpUid);
        }
        return $id;
    }

    /**
     * 退出对话
     *
     * @param int $u_id            
     * @param int $msgd_id            
     * @throws \Exception\ModelException
     * @return number
     */
    public function quitDialog($u_id, $msgd_id)
    {
        $uids = $this->getDialogUids($msgd_id);
        if (! $uids || ! isset($uids[$u_id])) {
            throw new \Exception\ServiceException("已移除");
        }
        unset($uids[$u_id]);
        return $this->update($msgd_id, array(
            'msgd_userIds' => implode(',', $uids)
        ));
    }

    /**
     * 从字符串中加载用户id数组
     *
     * @param string $uidsStr            
     * @return multitype: multitype:unknown
     */
    public function getUidsFromStr($uidsStr)
    {
        $uids = array();
        if (! $uidsStr) {
            return $uids;
        }
        $originalUids = explode(',', $uidsStr);
        foreach ($originalUids as $tmpUid) {
            if ($tmpUid) {
                $uids[$tmpUid] = $tmpUid;
            }
        }
        return $uids;
    }

    /**
     * 查询对话的用户ids数组
     *
     * @param int $msgd_id            
     * @return array
     */
    public function getDialogUids($msgd_id)
    {
        $uids = array();
        $row = $this->oneById($msgd_id);
        if (! $row) {
            return $uids;
        }
        $uidsStr = $row['msgd_userIds'];
        $uids = $this->getUidsFromStr($uidsStr);
        return $uids;
    }

    /**
     * 查询消息id是否存在
     *
     * [queryMsgdId description]
     *
     * @author :
     *         @dateTime 2017-04-19T09:53:32+0800
     * @copyright [copyright]
     * @license [license]
     * @version [version]
     * @param [type] $msgd_id
     *            [会话id]
     * @return [type] [受影响的行数]
     */
    public function queryMsgdId($msgd_id)
    {
        $row = $this->oneById($msgd_id);
        if (! $row) {
            return false;
        }
        return $row['msgd_id'];
    }

    /**
     * 使用uids，查询会话id
     *
     * @param unknown $uids            
     * @return boolean Ambigous
     */
    public function queryMsgdIdByUids($uids)
    {
        sort($uids);
        $row = $this->one("msgd_userIds=:userIds", 
            array(
                'userIds' => implode(',', $uids)
            ));
        if (! $row) {
            return false;
        }
        return $row['msgd_id'];
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
            $whereArr[] = 'dialog.msgd_id  in (' . $params['msgd_id'] . ') ';
        }
        if (isset($params['u_id']) && $params['u_id'] != '') {
            $whereArr[] = 'dialog.u_id = :u_id';
            $bindData[':u_id'] = $params['u_id'];
        }
        if (isset($params['createDateStart']) && $params['createDateStart'] != '') {
            $whereArr[] = '`dialog`.msgd_createDate >= :createDateStart';
            $bindData[':createDateStart'] = $params['createDateStart'];
        }
        if (isset($params['createDateEnd']) && $params['createDateEnd'] != '') {
            $whereArr[] = '`dialog`.msgd_createDate <= :createDateEnd';
            $bindData[':createDateEnd'] = $params['createDateEnd'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT dialog.* FROM `{$this->table}` dialog
                $where ORDER BY dialog.msgd_updateDate DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` dialog $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        foreach ($rows as $k => $row) {
            $rows[$k]['msgd_userIdsArr'] = $this->getUidsFromStr($row['msgd_userIds']);
        }
        return [
            $rows,
            $count
        ];
    }
}
