<?php
/**
 * 数据库模型的基类
 */
namespace Lib\Base;

use Framework\Lib\MySql;

class BaseModel
{

    protected $table = null;

    protected $id = null;

    private $mysqlDbFlag = 'mysql';

    protected $mysql = null;

    public function __construct($table = null, $id = null, $mysqlDbFlag = 'mysqlbxd_app')
    {
        if (! empty($table)) {
            $this->table = $table;
        }
        if (! empty($id)) {
            $this->id = $id;
        }
        $this->setMysql($mysqlDbFlag);
        
        if (empty($this->table) || empty($this->id)) {
            throw new \Exception('this model\'s property table and id can not empty');
        }
    }

    public function beginTransaction()
    {
        $this->mysql->beginTransaction();
    }

    public function commit()
    {
        $this->mysql->commit();
    }

    public function rollback()
    {
        $this->mysql->rollback();
    }


    public function inTransaction(){
       return   $this->mysql->inTransaction();
    }

    public function setMysql($mysqlDbFlag)
    {
        $this->mysql = app($mysqlDbFlag);
        $this->mysqlDbFlag = $mysqlDbFlag;
        if (! $this->mysql) {
            throw new \Exception("mysql没有连接");
        }
    }

    public function getMysqlDbFlag()
    {
        return $this->getMysqlDbFlag();
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
    public function update($id, $data)
    {
        $result = $this->mysql->update($this->table, $data, [
            $this->id => $id
        ]);
        return $result;
    }

    /**
     * replace数据
     * 
     * @param array $data
     *            数据
     * @return int 返回影响行数
     */
    public function replace($data)
    {
        $result = $this->mysql->replace($this->table, $data);
        return $result;
    }

    /**
     * 插入数据
     * 
     * @param array $data
     *            数据
     * @return array [0 => rowCount, 1 => lastInsertId]
     */
    public function insert($data)
    {
        $result = $this->mysql->insert($this->table, $data);
        return $result;
    }

    /**
     * 删除数据
     * 
     * @param int $id
     *            id
     * @return int 受影响的行数
     */
    public function delete($id)
    {
        $result = $this->mysql->delete($this->table, [
            $this->id => $id
        ], 1);
        return $result;
    }

    /**
     * 通过分页的方式查询数据
     * 
     * @param integer $page
     *            页码
     * @param integer $pagesize
     *            分页大小
     * @return array 数据集
     */
    public function select($page = 1, $pagesize = 30)
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $result = $this->mysql->selectPage($sql,$page,$pagesize);
        return $result;
    }

    /**
     * 查询所有数据
     * 
     * @return array 数据集
     */
    public function selectAll()
    {
        $sql = "SELECT * FROM {$this->table}";
        $result = $this->mysql->select($sql);
        return $result;
    }

    /**
     * 根据id获取单条的数据
     * 
     * @param integer $id
     *            id
     * @return array 单条数据
     */
    public function oneById($id)
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->id}` = :{$this->id} LIMIT 1";
        
        $result = $this->mysql->fetch($sql, [
            $this->id => $id
        ]);
        
        return $result;
    }

    /**
     * 获得行数
     * 
     * @param string $where
     *            where子句, 使用时不要带上where关键字
     * @param array $bindData
     *            绑定的数据
     * @return array 单条数据
     */
    public function one($where = '', $bindData = array())
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $sql .= $this->where($where);

        $result = $this->mysql->fetch($sql, $bindData);
        return $result;
    }

    /**
     * 获得行数
     * 
     * @param string $where
     *            where子句, 使用时不要带上where关键字
     * @param array $bindData
     *            绑定的数据
     * @return integer 结果数量
     */
    public function rowCount($where = '', $bindData = array())
    {
        $sql = "SELECT COUNT(0) FROM `{$this->table}`";
        $sql .= $this->where($where);
        $result = $this->mysql->fetchColumn($sql, $bindData);
        
        return $result;
    }

    /**
     * 创建where子句
     * 
     * @param string $where
     *            where子句
     * @return string 处理后的where子句
     */
    protected function where($where = '')
    {
        if ($where != '') {
            $where = ' WHERE ' . $where;
        }
        return $where;
    }

    /**
     * 创建sql SET子句
     * 
     * @param array $set
     *            字典数组
     */
    protected function set($set = array())
    {
        $keys = array_keys($set);
        $_sets = array_map(function ($r)
        {
            return "`$r` = :$r";
        }, $keys);
        
        $sets = implode(', ', $_sets);
        $bindData = array();
        foreach ($set as $key => $value) {
            $bindData[':' . $key] = $value;
        }
        return array(
            $sets,
            $bindData
        );
    }

    /**
     * 批量插入数据
     * 
     * @param array $data            
     * @return bool
     */
    public function batchInsert($data)
    {
        if (! empty($data)) {
            $keys = array_keys($data[0]);
            $columns = implode(',', $keys);
            $values = array();
            $bindData = array();
            foreach ($data as $key => $value) {
                $_values = array();
                foreach ($keys as $kk => $vv) {
                    $_values[] = ':' . $vv . $key;
                    $bindData[':' . $vv . $key] = $value[$vv];
                }
                $values[] = '(' . implode(',', $_values) . ')';
            }
            $valueStr = implode(',', $values);
            $sql = "INSERT INTO `{$this->table}`({$columns}) VALUES{$valueStr}";
            return $this->mysql->query($sql, $bindData);
        }
        return true;
    }

    /**
     * 得到该模型的表名
     * 
     * @return string $table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 得到该模型的主键
     * 
     * @return string $id
     */
    public function getPrimarykey()
    {
        return $this->id;
    }
}
