<?php
namespace Model\Found;

use Lib\Base\BaseModel;

class NearBy extends BaseModel
{

    protected $table = 'market';

    protected $id = 'm_id';

    /**
     * 新增市场
     *
     * @param string $m_name            
     * @param string $m_desc            
     * @param int $m_provinceCode            
     * @param int $m_cityCode            
     * @param int $m_areaCode            
     * @param number $m_latitude            
     * @param number $m_longitude            
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function add($m_name, $m_desc, $m_provinceCode, $m_cityCode, $m_areaCode, $m_latitude, $m_longitude)
    {
        if (! $m_name || ! $m_desc || ! $m_provinceCode || ! $m_cityCode) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        if (strlen($m_name) > 200) {
            throw new \Exception\ParamsInvalidException("标题过长");
        }
        $data = array(
            'm_name' => $m_name,
            'm_desc' => $m_desc,
            'm_provinceCode' => $m_provinceCode,
            'm_cityCode' => $m_cityCode,
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
        if (isset($params['m_id']) && $params['m_id'] != '') {
            $whereArr[] = 'uce.m_id = :m_id';
            $bindData[':m_id'] = $params['m_id'];
        }
        
        if (isset($params['m_provinceCode']) && $params['m_provinceCode'] != '') {
            $whereArr[] = 'uce.m_provinceCode = :m_provinceCode';
            $bindData[':m_provinceCode'] = $params['m_provinceCode'];
        }
        if (isset($params['m_cityCode']) && $params['m_cityCode'] != '') {
            $whereArr[] = 'uce.m_cityCode = :m_cityCode';
            $bindData[':m_cityCode'] = $params['m_cityCode'];
        }
        if (isset($params['m_areaCode']) && $params['m_areaCode'] != '') {
            $whereArr[] = 'uce.m_areaCode = :m_areaCode';
            $bindData[':m_areaCode'] = $params['m_areaCode'];
        }
        if (isset($params['m_isCelebrity']) && $params['m_isCelebrity'] != '') {
            $whereArr[] = 'uce.m_isCelebrity = :m_isCelebrity';
            $bindData[':m_isCelebrity'] = $params['m_isCelebrity'];
        }
        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);
        $sql = "SELECT uce.* FROM `{$this->table}` uce
                $where ORDER BY uce.m_id DESC ";
        $rows = app('mysqlbxd_app')->selectPage($sql,$page,$pagesize, $bindData);
        
        $countSql = "SELECT COUNT(0) FROM `{$this->table}` uce $where";
        $count = app('mysqlbxd_app')->fetchColumn($countSql, $bindData);
        return [
            $rows,
            $count
        ];
    }
}
