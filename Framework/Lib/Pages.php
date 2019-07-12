<?php

namespace Framework\Lib;

// 分页类

class Pages {
	private $total_item       = '';		//总记录数量
	private $page_size  	  = '';		//每页记录个数

	private $cur_page   	  = '';		//当前页
	private $total_page 	  = '';		//总页数
	private $display_page_num = 10;		//页面显示多少个页码连接

	private $url			  = '';		//url

	private $offset			  = 2;		//当前页面前面显示的页面数量

	/**
	 *	构造
	 */
	function __construct($cfg = []) {
		if(is_array($cfg)) {
			foreach($cfg AS $k => $v) {
				isset($this->$k) && ($this->$k = $v);
			}
		}
		$this->total_page       = $this->total_item > 0 ? ceil($this->total_item/$this->page_size) : 1;//总页数
		$this->display_page_num = ($this->display_page_num < 4)	? 4
																: $this->display_page_num;//显示页码的个数不能少于4个
		$this->cur_page = (($this->cur_page <= 0) || ($this->cur_page > $this->total_page))		?	1
																								:	$this->cur_page ;//防止人为输入不正确参数
	}

	/**
	 *	页码输出范围计算
	 */
	function scope() {
		$arr = array();
		//总页码数小于显示页码数
		if($this->total_page <= $this->display_page_num) {
			$arr['start'] = 1;
			$arr['end']   = $this->total_page;
			return $arr;
		}

		if(($this->cur_page - $this->offset) <= 1 ) {
			$arr['start'] = 1;
			$arr['end']   = $this->display_page_num;
			return $arr;
		} else {
			$arr['start'] = $this->cur_page - $this->offset;
			$t = $this->display_page_num - ($this->offset + 1);//显示的链接数量减去当前页和当前页的前两页 == 当前页后边显示的页码个数
			if(($this->cur_page + $t) >= $this->total_page) {
				$arr['end']   =  $this->total_page;
				$arr['start'] = $arr['start'] - (($this->cur_page + $t) - $this->total_page);
			} else {
				$arr['end']   = $this->cur_page + $t;
			}
			return $arr;
		}
	}


	/**
	 *	输出html代码
	 */
	function createLink() {
		//只有一页，直接返回
		if($this->total_page == 1) {
			return '';
		}
		$scope = $this->scope();
		$out   = '';
		$prev  = $next = 0;
		$prev  = $this->cur_page - 1;//上一页
		$next  = $this->cur_page + 1;//下一页

		//是否显示首页
		$out .= ($scope['start'] != 1)	?	'<a href="' . str_replace('%d', 1, $this->url) . '">首页</a>'
										:	'';
		//是否显示上一页
		$out .= ($this->cur_page != 1)	?	'<a href="' . str_replace('%d', $prev, $this->url) . '">上一页</a>'
										:	'';
		//中间页码部分
		for($i=$scope['start'];$i<=$scope['end'];$i++) {
			$out .= ($this->cur_page == $i)	? '<span>' . $i . '</span>'
											: '<a href="' . str_replace('%d', $i, $this->url) . '">'.$i.'</a>';
		}
		//是否显示末页
		$out .= ($scope['end'] != $this->total_page)	?	'<a href="' . str_replace('%d', $this->total_page, $this->url) . '">' . '末页(' . $this->total_page . ')</a>'
														:	'';
		//是否显示下一页
		$out .= ($this->cur_page != $this->total_page)	?	'<a href="' . str_replace('%d', $next, $this->url) . '">下一页</a>'
														:	'';
		echo $out;
	}



	/**
	 *	样式A分页
	 */
	function styleA() {
		//只有一页，直接返回
		if($this->total_page == 1) {
			return '';
		}
		$scope = $this->scope();
		$out  = '<div style="text-align:right;"><ul class="pagination">';
		$prev = $next = 0;
		$prev = $this->cur_page - 1;//上一页
		$next = $this->cur_page + 1;//下一页

		//是否显示首页
		$out .= ($scope['start'] != 1)	?	'<li><a href="' . str_replace('%d', 1, $this->url) . '">首页</a></li>'
										:	'';
		//是否显示上一页
		$out .= ($this->cur_page != 1)	?	'<li><a href="' . str_replace('%d', $prev, $this->url) . '">上一页</a></li>'
										:	'';
		//中间页码部分
		for($i=$scope['start'];$i<=$scope['end'];$i++) {
			$out .= ($this->cur_page == $i)	? '<li class="active"><a href="javascript:;">' . $i . '</a><li>'
											: '<li><a href="' . str_replace('%d', $i, $this->url) . '">'.$i.'</a></li>';
		}
		//是否显示末页
		$out .= ($scope['end'] != $this->total_page)	?	'<li><a href="' . str_replace('%d', $this->total_page, $this->url) . '">' . '末页(' . $this->total_page . ')</a></li>'
														:	'';
		//是否显示下一页
		$out .= ($this->cur_page != $this->total_page)	?	'<li><a href="' . str_replace('%d', $next, $this->url) . '">下一页</a></li>'
														:	'';
		$out .= '</ul></div>';
		return $out;
	}

	/**
	 *	样式B分页
	 */
	function styleB() {
		//只有一页，直接返回
		if($this->total_page == 1) {
			return '';
		}
		$scope = $this->scope();
		$out  = '<div class="sort"><p>共<span> '.$this->total_item.' </span>条记录</p><ul class="sort_page">';
		$prev = $next = 0;
		$prev = $this->cur_page - 1;//上一页
		$next = $this->cur_page + 1;//下一页

		//是否显示首页
		$out .= ($scope['start'] != 1)	?	'<li><a href="' . str_replace('%d', 1, $this->url) . '">首页</a></li>'
										:	'';
		//是否显示上一页
		$out .= ($this->cur_page != 1)	?	'<li><a href="' . str_replace('%d', $prev, $this->url) . '">上一页</a></li>'
										:	'';
		//中间页码部分
		for($i=$scope['start'];$i<=$scope['end'];$i++) {
			$out .= ($this->cur_page == $i)	? '<li class="active"><span>' . $i . '</span></li>'
											: '<li><a href="' . str_replace('%d', $i, $this->url) . '">'.$i.'</a></li>';
		}
		//是否显示末页
		$out .= ($scope['end'] != $this->total_page)	?	'<li><a href="' . str_replace('%d', $this->total_page, $this->url) . '">' . '末页(' . $this->total_page . ')</a></li>'
														:	'';
		//是否显示下一页
		$out .= ($this->cur_page != $this->total_page)	?	'<li><a href="' . str_replace('%d', $next, $this->url) . '">下一页</a></li>'
														:	'';
		$out .= '</ul></div>';
		return $out;
	}

}