<?php
/*************************************
*
**************************************/
namespace Framework\Lib;

//!defined('IN_YK') && exit('Deny');

use Exception\ParamsInvalidException;
use PDO;

class MySql
{
	public $mConn;
    /**
     * @var \PDOStatement
     */
	public $mState; //PDOStatement obj
	private $mFetchStyle = PDO::FETCH_ASSOC;
	private $mPrefix;

	function __construct()
	{
		// var_dump('mysql construct..');
	}

	/**
	 *  设置查询索引类型
	 * @param $fetchStyle PDO::FETCH_ASSOC(default)，PDO::FETCH_NUM， PDO::FETCH_BOTH
	 * @return 当前对象
	 */
	public function setFetchStyle($fetchStyle)
	{
		$this->mFetchStyle = $fetchStyle;
		return $this;
	}

	/**
	 * 连接数据库
	 * pdo 链接 参考 http://php.net/manual/en/pdo.connections.php
	 * @param string $dbHost
	 * @param string $dbName
	 * @param string $dbUser
	 * @param string $dbPass
	 * @return void
	 */
	public function connect($dbHost = '', $dbUser = '', $dbPass = '', $dbName = '', $prefix = 'blim_', $dbPort = '3306')
	{
		// var_dump('mysql connect...');
		if ($this->mConn) {
			return;
		}
		try {
			$this->mConn = new PDO("mysql:host={$dbHost};dbname={$dbName};port={$dbPort};charset=UTF8MB4;", $dbUser, $dbPass);
			$this->mConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			$this->mConn = null;
			throw $e;
		}
		$this->mPrefix = $prefix;
		return $this;
	}

	/**
	 * 关闭数据库连接，当您使用持续连接时该功能失效
	 *
	 * @return blooean
	 */
	public function close()
	{
		if ($this->mConn)
		{
			$this->mConn = null;
		}
		return $this;
	}

	/**
	 *
	 * 事务的隔离等级（Isolation levels）跟数据相关 相关阅读：
	 *  https://dev.mysql.com/doc/refman/5.7/en/innodb-transaction-isolation-levels.html
	 *
	 */
	public function beginTransaction()
	{
		return $this->mConn->beginTransaction();
	}

	public function commit()
	{
		return $this->mConn->commit();
	}

	public function rollback()
	{
		return $this->mConn->rollback();
	}


	public function inTransaction(){
		return  $this->mConn->inTransaction();
	}

	/**
	 *
	 * @param $sql
	 * @param $bindData
	 * @return a PDOStatement obj
	 */
	public function query($sql, $bindData = array())
	{
		$res = null;
		try {
			$this->mState = $this->mConn->prepare($sql);
			// var_dump($sql, $bindData);
			$this->bindValues($this->mState, $bindData);
			$res = $this->mState->execute();
		} catch (\PDOException $e) {
			throw $e;
		}
		return $res;
	}

	private function bindValues($PDOStatement, $bindData = array())
	{
		$dataType = PDO::PARAM_STR;
		foreach ($bindData as $key => $value) {
		    if(is_int($value)){
		        $dataType=PDO::PARAM_INT;
		    }else{
		        $dataType=PDO::PARAM_STR;
		    }
			$res = $PDOStatement->bindValue($key, $value, $dataType);
			if (!$res) {
				throw new \Exception\ModelException("failed on bind array value key={$key} value={$value}");
			}
		}
		return $PDOStatement;
	}

	/**
	 * select
	 * @param $sql sql语句
	 * @param $bindData 绑定数组 array()
	 * @return array数据
	 */
	public function select($sql, $bindData = array())
	{
		$this->query($sql, $bindData);
		return $this->mState->fetchAll($this->mFetchStyle);
	}

    /**
     * selectPage
     * @param $sql
     * @param $page
     * @param $pageSize
     * @param array $bindData
     */
    public function selectPage($sql,$page,$pageSize,$bindData=[])
    {
        $page=intval($page);
        $pageSize=intval($pageSize);
        $offset=($page - 1) * $pageSize;
        $offset=$offset<0?0:$offset;
        $sql .= ' limit :offset , :limit';
        $bindData['offset']=intval($offset);
        $bindData['limit']=intval($pageSize);
        if($bindData['limit']>100){
            throw new ParamsInvalidException('pageSize过大');
        }
        if($bindData['limit']<0){
            throw new ParamsInvalidException('pageSize过小');
        }

        $this->query($sql, $bindData);
        return $this->mState->fetchAll($this->mFetchStyle);
    }

    public function fetch($sql, $bindData = array())
	{
		$this->query($sql, $bindData);
		$data= $this->mState->fetch($this->mFetchStyle);
        $this->mState->closeCursor();
        return $data;
	}

    public function fetchColumn($sql, $bindData = array())
	{
		$this->query($sql, $bindData);
		return $this->mState->fetchColumn();
	}

    /**
	 *
	 *
	 * @return 返回影响行数
	 */
	public function update($table, $data, $condition)
	{
		$sql = "UPDATE `{$this->mPrefix}{$table}` ";
		list($setSql, $bindParams) = $this->arrToSetSql($data);
		$sql .= $setSql;
		list($conSql, $bindParams1) = $this->arrayToConSql($condition);
		$sql .= $conSql;
		$bindParams = array_merge($bindParams, $bindParams1);
		$this->query($sql, $bindParams);
		return $this->mState->rowCount();
	}

    /**
     * @return mixed 返回影响的行数
     */
	public function getEffectRowCount()
    {
        return $this->mState->rowCount();
    }

	/**
	 * 插入数据， 仅支持一条数据
	 * @param table table name
	 * @param data one coulume data
	 * @return array(影响行数, 最后插入id)
	 */
	public function insert($table, $data)
	{
		$sql = "INSERT INTO `{$this->mPrefix}{$table}`";
		list($setSql, $bindParams) = $this->arrToSetSql($data);
		$sql .= $setSql;
		$this->query($sql, $bindParams);
		return array($this->mState->rowCount(), $this->mConn->lastInsertId());
	}

	public function replace($table, $data)
	{
		$sql = "REPLACE INTO `{$this->mPrefix}{$table}`";
		list($setSql, $bindParams) = $this->arrToSetSql($data);
		$sql .= $setSql;
		$this->query($sql, $bindParams);
		return $this->mState->rowCount();
	}

	public function delete($table, $condition)
	{
		$sql = "DELETE FROM `{$this->mPrefix}{$table}`";
		list($conSql, $bindParams) = $this->arrayToConSql($condition);
		$sql .= $conSql;
		$this->query($sql, $bindParams);
		return $this->mState->rowCount();
	}

	private function arrToSetSql($data)
	{
		$bindParams = array();
		$setSql = ' SET ';
		$setTempSql = '';
		foreach ($data as $key => $value) {
			$setTempSql .= ",`{$key}`= :{$key} ";
			$bindParams[":{$key}"] = $value;
		}
		$setTempSql = empty($setTempSql) ? '' : substr($setTempSql, 1);
		$setSql .= $setTempSql;
		return array($setSql, $bindParams);
	}

	private function arrayToConSql($data)
	{
		$bindParams = array();
		$conSql = ' WHERE ';
		$tempSql = '';
		foreach ($data as $key => $value) {
			$tempSql .= " AND `{$key}`= :{$key} ";
			$bindParams[":{$key}"] = $value;
		}
		$tempSql = empty($tempSql) ? ' 0 ' : substr($tempSql, 4);
		$conSql .= $tempSql;
		return array($conSql, $bindParams);
	}
}
