<?php

namespace Framework\Lib;

class SimpleModel
{

	// 数据库名
	protected $dbName    = '';

	// 数据表名
	protected $tableName = '';

	// db
	protected $db        = '';

	// queryTable
	protected $queryTable = '';

	// __construct
	public function __construct($name = '', $db=null) {
		if (!empty($name)) {
			$tmpArr = explode('.', $name);
			if (is_array($tmpArr) && !empty($tmpArr[0]) && !empty($tmpArr[1])) {
				$this->dbName    = $tmpArr[0];
				$this->tableName = $tmpArr[1];
			} else {
				$this->tableName = $name;
			}
		}
        if(is_null($db)){
    		$this->db = app('mysqlbxd_mall_common');
        }else {
            $this->db=$db;
        }
		$this->queryTable = $this->getTabName();
	}
    public function setDb($db)
    {
        $this->db=$db;
    }
	// 获取表名称
	public function getTabName(){
		$tabName = (empty($this->dbName) ? '' : "`{$this->dbName}`.") . "`{$this->tableName}`";
		return $tabName;
	}

	// findAll
	public function findAll($field = '', $where = '', $whereArr = array(), $orderBy = '', $limit = '1000', $returnByKey = '') {
		$field   = empty($field) ? '*' : ( strpos($field, '(') === false ? '`' .str_replace(',', '`,`', $field). '`' : $field);
		empty($where) && ($where = '1');
		empty($limit) && ($limit = '1000');

		$sql	 = "SELECT {$field} FROM {$this->queryTable} WHERE {$where}";
		$orderBy && $sql .= " ORDER BY {$orderBy} ";
		$sql             .= " LIMIT {$limit}";
		$querySt = $this->db->query($sql, $whereArr);
		$res     = $this->db->mState->fetchAll(\PDO::FETCH_ASSOC);

		// 返回的结果数组以哪个字段作为一维的key 【！！该参数必须唯一！！】
		if (!empty($returnByKey) && ($field == '*' || strpos($field, $returnByKey) !== false)) {
			$return = [];
			foreach ($res as $k => $v) {
				$return[$v[$returnByKey]] = $v;
			}
			return $return;
		}

		return $res;
	}

	// findOne
	public function findOne($field = '', $where = '', $whereArr = array(), $orderBy = '') {
		$res = $this->findAll($field, $where, $whereArr, $orderBy, '1');
		return isset($res[0]) ? $res[0] : array();
	}

	// insert
	public function insert($dataArr = array()) {
		$arrKs    = array_keys($dataArr);
		$fieldStr = '`' .implode('`,`',  $arrKs). '`';
		$valueStr = ":" .implode(",:",   $arrKs);
		$bindArr  = array();
		array_walk($dataArr, function($v, $k) use(&$bindArr){
			$bindArr[":{$k}"] = $v;
		});

		$sql     = "INSERT INTO {$this->queryTable}({$fieldStr}) VALUES({$valueStr})";
		$querySt = $this->db->query($sql, $bindArr);
		return $this->db->mConn->lastInsertId();
	}


	// update(容易出现问题，upArr和where中参数同名情况下)
	public function update($upArr = array(), $where = '', $whereArr = array()) {
		$setStr  = '';
		$bindArr = array();
		foreach ($upArr as $k => $v) {
			$setStr           .= "`{$k}` = :{$k},";
			$bindArr[":{$k}"] = $v;
		}

		$setStr = rtrim($setStr, ',');
		if (empty($where)) {
			$whereArr = array();
			$where    = '0';
		}
		$bindArr += $whereArr;

		$sql     = "UPDATE {$this->queryTable} SET {$setStr} WHERE {$where}";
		$querySt = $this->db->query($sql, $bindArr);
		return $this->db->mState->rowCount();
	}


	// delete
	public function delete($where = '', $whereArr = array()) {
		if(empty($where)) {
			$where    = '0';
			$whereArr = array();
		}
		$sql     = "DELETE FROM {$this->queryTable} WHERE {$where}";
		$querySt = $this->db->query($sql, $whereArr);
		return $this->db->mState->rowCount();
	}

	// getCount
	public function getCount($where = '', $whereArr = array()) {
		if(empty($where)){
			$where    = '1';
			$whereArr = array();
		}

		$sql     = "SELECT COUNT(*) AS num FROM {$this->queryTable} WHERE {$where}";
		$querySt = $this->db->query($sql, $whereArr);
		$res     = $this->db->mState->fetchAll(\PDO::FETCH_ASSOC);
		return isset($res[0]['num']) ? $res[0]['num'] : 0;
	}

	// getList 获取列表
	function getList($params = []) {
		$curPage     = isset($params['curPage'])  	  ? intval($params['curPage'])  : 1;
		$pageSize    = isset($params['pageSize']) 	  ? $params['pageSize'] : 10;
		$field       = isset($params['field'])    	  ? $params['field']    : '';
		$where       = isset($params['where'])    	  ? $params['where']    : '';
		$whereArr    = isset($params['whereArr']) 	  ? $params['whereArr'] : [];
		$orderBy     = isset($params['orderBy'])  	  ? $params['orderBy']  : '';
		$returnByKey = isset($params['returnByKey'])  ? $params['returnByKey']  : '';
		$limit       = ($curPage - 1) * $pageSize .','. $pageSize;
		($curPage <= 1) && $curPage = 1;

		$totalNum = $this->getCount($where, $whereArr);
		$listArr  = $this->findAll($field, $where, $whereArr, $orderBy, $limit, $returnByKey);

		return [
			'totalNum' => $totalNum,
			'listArr'  => $listArr,
		];
	}


	// run sql
	function runSQL($sql = '', $bindData = []) {
		if ($sql) {
			return $this->db->select($sql, $bindData);
		}
	}



}