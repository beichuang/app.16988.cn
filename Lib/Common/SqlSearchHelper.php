<?php
namespace Lib\Common;

class SqlSearchHelper
{

    public $whereArr = [];

    public $bindDataArr = [];

    private $mysql = null;

    public function __construct($dbFlag = 'mysql')
    {
        $this->mysql = app($dbFlag);
    }

    private function uniqid($prefix)
    {
        $id = uniqid($prefix);
        while ($id == uniqid($prefix)) {
            $id = uniqid($prefix);
        }
        return $id;
    }

    public function push($key, $value, $op = '=')
    {
        if (is_array($value)) {
            if (isset($value[$key]) && $value[$key] != '') {
                $value = $value[$key];
            }
        }
        $bindDataKey = $this->uniqid("{$key}_");
        $this->where("{$key} {$op} :{$bindDataKey}");
        $this->bind($bindDataKey, $value);
    }

    public function where($where)
    {
        $this->whereArr[] = $where;
    }

    public function bind($key, $value)
    {
        $this->bindData[$key] = $value;
    }

    public function getConditions($op = 'and')
    {
        if (! empty($this->whereArr)) {
            return implode(" {$op} ", $this->whereArr);
        }
        return '';
    }

    public function getBindData()
    {
        return $this->bindDataArr;
    }

    public function queryByPage($table, $page, $pagesize, $op = 'and')
    {
        $sql = "SELECT * FROM `{$table}`  {$where} ";
        $skip = ($page - 1) * $pagesize;
        $limit = " LIMIT {$skip}, {$pagesize} ";
        $sql .= $limit;
        $rows = app('mysqlbxd_user')->select($sql, $this->getBindData());
        return $rows;
        $count = 0;
        $skip = ($page - 1) * $pagesize;
        $limit = " LIMIT {$skip}, {$pagesize} ";
        $sql .= $limit;
        $countSql = "SELECT COUNT(0) FROM `{$table}` " . $this->getConditions($op);
        $count = $this->mysql->fetchColumn($countSql, $this->getBindData());
    }

    public function count($table, $op = 'and')
    {
        $countSql = "SELECT COUNT(0) FROM `{$table}` " . $this->getConditions($op);
        return $this->mysql->fetchColumn($countSql, $this->getBindData());
    }
}
