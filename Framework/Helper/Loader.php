<?php

namespace Framework\Helper;

// 加载类
class Loader
{

	/**
	 * Model Object Array
	 * @var array
	 */
	private static $modelObjArr = array();

	/**
	 * 加载model
	 * @param string $modelName model名
	 * @param string $dbName    数据库名
	 */
	public static function M($modelName = '', $dbName = '') {

		$modelClass = "\\Framework\\Model\\{$modelName}";
		$params     = [];

		// 是否已经生成过
		if ( ! isset(self::$modelObjArr[$modelName]) || ! is_object(self::$modelObjArr[$modelName]) ) {
			try {
				$refObj                        = new \ReflectionClass($modelClass);
				self::$modelObjArr[$modelName] = $refObj->newInstanceArgs($params);
			} catch (\ReflectionException $e) {

				// 无该model，则尝试解析model name为表名
				if (!empty($modelName) && $e->getCode() == '-1') {
					$modelClass = "\\Framework\\Lib\\SimpleModel";
					$params     = preg_replace_callback('/[A-Z]/', function($match){ return '_'. $match[0]; }, $modelName);
					$params     = (empty($dbName) ? '' : "{$dbName}."). strtolower(ltrim($params, '_'));
					$refObj     = new \ReflectionClass($modelClass);
					self::$modelObjArr[$modelName] = $refObj->newInstanceArgs((array)$params);
				} else {
					// 其他情况继续抛异常
					throw $e;
				}
			}
		}

		return self::$modelObjArr[$modelName];
	}




}