<?php

namespace Framework\Lib;

// 公共函数集合

use Framework\Helper\Loader;
use Framework\Helper\SessionHelper;

class CommonFun
{

	/**
	 * 功能说明：根据用户id获取用户头像url地址，支持批量获取。
	 *
	 * @param int $uid :用户id
	 * @return str/array
	 *
	 */
	static function getUserAvatarByUid($uid = 0) {
		$uidArr = is_array($uid) ? $uid : (array) $uid;
		$retArr = array();
		$domain = rtrim(config('app.userAvatarDomain'), '/') ;

		foreach ($uidArr as $k => $_uid) {
			$_uid = intval($_uid);
			if ($_uid > 0) {
				$tmpDir = ceil($_uid / 10000) * 10000;
				$retArr[$_uid] = $domain . "/{$tmpDir}/{$_uid}.jpg";
			}
		}

		if (count($retArr) > 1) {
			return $retArr;
		}

		$tmpArr = each($retArr);
		return $tmpArr['value'];
	}


	/**
	 * 功能说明： base64url_encode
	 *
	 * @param type varName :value
	 * @return void
	 *
	 */
	static function base64url_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * 功能说明： base64url_decode
	 *
	 * @param type varName :value
	 * @return void
	 *
	 */
	static function base64url_decode($data) {
		return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
	}


	/**
	 * 功能说明： 以数组某key为一维key
	 *
	 * @param type varName :value
	 * @return void
	 *
	 */
	static function arrangeArrByKey($data = [], $key = '') {
		if (!empty($data) && !empty($key)) {
			$newData = [];
			foreach ($data as $k => $v) {
				if (isset($v[$key])) {
					$newData[$v[$key]] = $v;
				}
			}
			return $newData;
		}
	}

	/**
	 * 记录日志
	 * @param  string  $fn           日志文件名
	 * @param  string  $cnt          日志内容，array格式内容会自动转换为string
	 * @param  boolean $withDate     日志中是否要加日期时间，true时每一项日志记录前会增加当前日期和时间。默认增加。
	 * @return int  写入日志文件的长度
	 */
	public static function wlogs($fn = '', $cnt = '', $withDate = true) {
	    if ($fn && $cnt) {
			$f      =  PRO_ROOT. "/Data/Logs/{$fn}_" .date('Y-m-d'). ".log";
			$date   = $withDate === true ? (PHP_EOL .'----------' .date('Y-m-d H:i:s'). '---------------' .PHP_EOL) : PHP_EOL;
			$cntStr =  $date. (is_array($cnt) ? var_export($cnt, true) : $cnt);
	        return file_put_contents($f, $cntStr, FILE_APPEND);
	    }
	}

	/**
	 * 功能说明：根据代理人id 获取代理人信息
	 * @param $uid 代理人id
	 *
	 */
	static function agentInfo($uid)
	{
		$result = app('mysqlbxd_app')->fetch("select ua.*,u_telphone,ue_realname from user_agent as ua LEFT JOIN user as u on ua.u_id = u.u_id LEFT JOIN user_extend as ue on ua.u_id = ue.u_id where ua.u_id = '$uid'");
		return $result ? : array();
	}

	/**
	 *  meng
	 * 1、清除多余附件（ftp 附件表）2、执行ftp上传，将本地的文件上传到ftp(编辑器里的),替换附件表父id，去掉am_sign，将设置为远程
	 * @param $content  编辑器里的内容 string
	 * @param $parentId  父id
	 * @param $flagId 模块id（附件所属模块）
	 */
	static function editFileToFtp($content, $parentId, $flagId = 0)
	{
		#上传新数据
		$img = preg_match_all('/\[attachment=(.*?)\]/',$content,$ar);
		if ($img) {
			$am_id = implode(',',$ar[1]);
			$sql = "select * from zsv6_attachment WHERE am_id IN ({$am_id}) AND am_remote = 0";
			$atta = app('mysqlbxd_app')->select($sql);
			foreach ($atta as $k => $v) {
				\Framework\Helper\File::moveToFtp(PRO_ROOT.config('app')['uploadTempFolder'].basename($v['am_path']),$v['am_path']);
			}
			app('mysqlbxd_app')->query("update zsv6_attachment set am_sign = '',am_remote=1,am_parentid = $parentId WHERE am_id IN ({$am_id})");
			return true;
		}
		#清理老数据
		$sql = "select * from zsv6_attachment WHERE am_parentid = {$parentId} AND am_flag = {$flagId}";
		$allAtta = app('mysqlbxd_app')->select($sql);
		$allAttaId = array_column($allAtta,'am_id');
		$allAttaId = array_diff($allAttaId,$ar[1]);
		if ($allAttaId) { //当前下的不用的老附件
				foreach ($allAtta as $k=>$v) { //删除ftp上多余附件
					if ($v['am_remote'] == 1) {
						if (app('ftp')->size($v['am_path']) != -1) { //ftp上存在该文件，则删除
							app('ftp')->delete($v['am_path']);
						}

					}
				}

				#删除附件表里多余附件
				$delAttaIdStr = implode(',',$allAttaId);
				Loader::M('zsv6_attachment')->delete("am_id in ({$delAttaIdStr})");
		}
	}

	/**
	 * 获取指定长度随机字符串
	 * @param  integer $len 随机字符串长度
	 * @return str
	 */
	public static function getRand($len = 6)
	{
		$len    = intval($len);
		($len <= 0) && ($len = 6);
		$str    = 'abcdefghijklmnopqrstuvwxy0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$%^&*()-=_+';
		$array  = str_split($str);
		$retStr = '';
		while ($len--) {
			$retStr .= $array[array_rand($array)];
		}

		return $retStr;
	}


	/**
	 * 检查权限
	 * @param  string $controller 控制器名称
	 * @param  string $action     action名称
	 * @return void
	 */
	public static function chkPermission($controller = '', $action = '')
	{
		$contArr       = explode('\\', $controller);
		$controller    = array_pop($contArr);
		$permissionArr = SessionHelper::exists('permission') ? SessionHelper::get('permission') : [];
		if (
			$permissionArr == 'all' ||
			($controller == 'Login' && in_array($action, ['index','login','logout']))
		   ) {
			return;
		}

		$chkSt = in_array(strtolower($controller.'/'.$action), $permissionArr, true);
		if (!$chkSt) {
			die('无权限');
		}
	}


	/**
	 * 获取当前访问url 用于自动获取分页
	 * @return tring
	 */
	public static function getCurUrl($additionPage = true)
	{
		$url = app('request')->getPath(). (empty($_SERVER['QUERY_STRING']) ? '' : '?'.$_SERVER['QUERY_STRING']);
		if ($additionPage) {
			$url =preg_replace('/[?&]page=\d+/', '', $url);
			$url .= (strpos($url, '?') === false) ? '?page=%d' : '&page=%d';
		}

		return $url;
	}

	/**
	 * 用于生成平面树的option
	 * @param $arr
	 * @param int    $id         节点id的键名
	 * @param string $name       节点名称的键名
	 * @param string $child      子节点的键名
	 * @param int|array    $selectedId 默认选中的id
	 * @param int    $depth      当$depth为0的时候表示不限制深度
	 * @return string
	 */
	public static function makeOptionTreeForSelect($arr, $id, $name, $child, $selectedId = 0, $depth = 0)
	{
	    $arr = self::makeTreeWithNamepre($arr, $name, $child);
	    return self::makeOptions1($arr, $id, $name, $child, $selectedId, $depth);
	}

	/**
	 * 把存在父子关系的数组整理为带前缀的树
	 * @param array $arr 原始数组,已整理为树形结构的数组
	 * @param string $name 节点名称的键名
	 * @param string $child 子节点的键名
	 * @return array
	 */
	public static function makeTreeWithNamepre($arr, $name, $child, $prestr='')
	{
	    $new_arr = array();
	    foreach ($arr as $v) {
	        if ($prestr) {
	            if ($v == end($arr)) {
	                $v[$name] = $prestr.'└─ '.$v[$name];
	            } else {
	                $v[$name] = $prestr.'├─ '.$v[$name];
	            }
	        }

	        if ($prestr == '') {
	            $prestr_for_children = '　 ';
	        } else {
	            if ($v == end($arr)) {
	                $prestr_for_children = $prestr.'　　 ';
	            } else {
	                $prestr_for_children = $prestr.'│　 ';
	            }
	        }
	        $v[$child] = self::makeTreeWithNamepre($v[$child], $name, $child, $prestr_for_children);

	        $new_arr[] = $v;
	    }
	    return $new_arr;
	}

	/**
	 * 生成平面树的option
	 * 返回数据样例
	 * <option value='1' data-depth='1' data-ancestor_ids=''>系统管理</option>
	 * <option value='2' data-depth='2' data-ancestor_ids='1'>　 ├─ 用户管理</option>
	 * @param  array   $arr             原始数据
	 * @param  string  $id              数据中id的key
	 * @param  string  $name            数据中节点名称的key
	 * @param  string  $child           子节点的key
	 * @param  int|array     $selectedId      默认选中的id
	 * @param  string  $depth           层级深度
	 * @return string
	 */
	public static function makeOptions1($arr, $id, $name, $child, $selectedId, $depth, $recursion_count=0, $ancestor_ids='')
	{
	    $recursion_count++;
	    $str = '';
	    foreach ($arr as $v) {
	        $selected = false;
	        if (is_array($selectedId)) {
	            if (in_array($v[$id], $selectedId)) {
	                $str .= "<option value='{$v[$id]}' selected='selected' data-depth='{$recursion_count}' data-ancestor_ids='".ltrim($ancestor_ids,',')."'>{$v[$name]}</option>";
	            } else {
	                $str .= "<option value='{$v[$id]}' data-depth='{$recursion_count}' data-ancestor_ids='".ltrim($ancestor_ids,',')."'>{$v[$name]}</option>";
	            }
	        } else {
	            if ($selectedId > 0 && $selectedId == $v[$id]) {
	                $str .= "<option value='{$v[$id]}' selected='selected' data-depth='{$recursion_count}' data-ancestor_ids='".ltrim($ancestor_ids,',')."'>{$v[$name]}</option>";
	            } else {
	                $str .= "<option value='{$v[$id]}' data-depth='{$recursion_count}' data-ancestor_ids='".ltrim($ancestor_ids,',')."'>{$v[$name]}</option>";
	            }
	        }
	        // if ($v->parent_id == 0) {
	        //     $recursion_count = 1;
	        // }
	        if ($depth==0 || $recursion_count<$depth) {
	            $str .= self::makeOptions1($v[$child], $id, $name, $child, $selectedId, $depth, $recursion_count, $ancestor_ids.','.$v[$id]);
	        }

	    }
	    return $str;
	}

	/**
	 * 从提交的post数据中获取所需的数据
	 * @param  array  $fieldCfg 字段名称&初级过滤方式
	 * @param  array  $postData post提交数据
	 * @return array
	 */
	static public function getDataByPost($fieldCfg = [], $postData = [])
	{
		$return = [];
		foreach ($fieldCfg as $k => $v) {
			$_data = isset($postData[$k]) ? $postData[$k] : '';
			$return[$k] = self::handleData($_data, $v);
		}
		return $return;
	}

	/**
	 * 对数据进行初级过滤
	 * @param  string $data   要处理的数据
	 * @param  string $filter 过滤的方式
	 * @return mix
	 */
	static public function handleData($data = '', $filter = '')
	{
		switch ($filter) {
			case 'int':
				return abs(intval($data));
			break;

			case 'str':
				return trim( htmlspecialchars( strip_tags($data) ) );
			break;

			case 'float':
				return floatval($data);
			break;

			case 'arr':
				return (array) $data;
			break;
		}

		return '';
	}

}