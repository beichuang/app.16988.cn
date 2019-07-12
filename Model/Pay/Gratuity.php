<?php
namespace Model\Pay;

use Lib\Base\BaseModel;
use Exception\ServiceException;

/**
 * 打赏日志
 */
class Gratuity extends BaseModel
{

    protected $table = 'gratuity_log';

    protected $id = 'gl_id';


    public function __construct()
    {
        parent::__construct($table = 'gratuity_log', $id = 'gl_id', $mysqlDbFlag = 'mysqlbxd_pay_center');
    }



    /**
     * 新增打赏
     *
     * @param int $uid
     * @param int $amount
     * @param int $g_id
     * @param int $g_salesId
     * @param string $content
     * @throws \Exception\ModelException
     * @return multitype:
     */
    public function write($uid, $amount, $g_id, $g_salesId, $content = '打赏了')
    {
        if (! $uid || ! $amount || ! $g_id || ! $g_salesId ) {
            throw new \Exception\ParamsInvalidException("缺少参数！");
        }
        $data = array(
            'u_id' => $uid,
            'gl_amount' => $amount,
            'gl_desc' => $content,
            'g_salesId' => $g_salesId,
            'g_id' => $g_id,
            'gl_time' => date('Y-m-d H:i:s')
        );
        list ($count, $id) = $this->insert($data);
        if ($id) {
            return $id;
        } else {
            return false;
        }
    }

    /**
     * 根据用户id查询最近的一次打赏
     *
     * @param int $uid
     * @return array boolean
     */
    public function getLastGratuityByUid($uid)
    {
        $sql = "select * from {$this->table} where u_id=:u_id order by `{$this->id}` desc limit 1";
        $row = $this->mysql->fetch($sql, array(
            'u_id' => $uid
        ));
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
        //g_id, g_salesId
        $whereArr = $bindData = [];

        if (isset($params['g_id']) && !empty($params['g_id'])) {
            $whereArr[] = 'gl.g_id = :g_id';
            $bindData[':g_id'] = $params['g_id'];
        }

        if (isset($params['g_salesId']) && !empty($params['g_salesId'])) {
            $whereArr[] = 'gl.g_salesId = :g_salesId';
            $bindData[':g_salesId'] = $params['g_salesId'];
        }


        $where = implode(' AND ', $whereArr);
        $where = $this->where($where);

        $sql = "SELECT gl.* FROM `{$this->table}` gl
                $where ORDER BY gl.gl_amount DESC ";
        $rows = $this->mysql->selectPage($sql,$page,$pagesize, $bindData);

        $countSql = "SELECT COUNT(*) FROM `{$this->table}` gl $where";
        $count = $this->mysql->fetchColumn($countSql, $bindData);

        return [
            $rows,
            $count
        ];
    }

}
