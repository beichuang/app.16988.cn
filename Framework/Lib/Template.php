<?php

namespace Framework\Lib;

class Template
{
	public $mVarRegexp = "\@?\\\$[a-zA-Z_][\\\$\w]*(?:\[[\w\-\.\"\'\[\]\$]+\])*";
	public $mVtagRegexp = "\<\?php echo (\@?\\\$[a-zA-Z_][\\\$\w]*(?:\[[\w\-\.\"\'\[\]\$]+\])*)\;\?\>";
	public $mConstRegexp = "([A-Z_][A-Z0-9_]+)";

	function __construct(){}

	/**
	*  读模板页进行替换后写入到cache页里
	*
	* @param string $tplFile ：模板源文件地址
	* @param string $objFile ：模板cache文件地址
	* @return string
	*/
	function complie($tplFile, $objFile)
	{

		$template = file_get_contents($tplFile);
		$template = $this->parse($template);

		\Framework\Helper\File::mkdir(dirname($objFile));
		\Framework\Helper\File::write($objFile, $template, $mod = 'w', TRUE);
	}

    public function delComments($template)
    {
        return preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template);//去除html注释符号<!---->
    }

    public function replaceVar($template)
    {
        return preg_replace("/\{($this->mVarRegexp)\}/", "<?php echo \\1;?>", $template);//替换带{}的变量
    }
    public function replaceConstant($template)
    {
        return preg_replace("/\{($this->mConstRegexp)\}/", "<?php echo \\1;?>", $template);//替换带{}的常量
    }
    public function replacePHP($template)
    {
        return preg_replace_callback("/\{php (.*?)\}/is", function ($m) {
            return $this->stripVtag('<?php '.$m[1].'?>');
        }, $template);//替换php标签
    }
    public function replaceFor($template)
    {
        return preg_replace_callback("/\{for (.*?)\}/is", function ($m) {
            return $this->stripVtag('<?php for ('.$m[1].') { ?>');
        }, $template);//替换for标签
    }
    public function replaceElseif($template)
    {
        return preg_replace_callback("/\{elseif\s+(.+?)\}/is", function ($m) {
            return $this->stripVtag('<?php } elseif ('.$m[1].') { ?>');
        }, $template);//替换elseif标签
    }
    public function replaceLoop($template)
    {
		for ($i=0; $i<3; $i++)
		{
            $template = preg_replace_callback("/\{loop\s+($this->mVarRegexp)\s+($this->mVarRegexp)\s+($this->mVarRegexp)\}(.+?)\{\/loop\}/is", function ($m) {
                return $this->loopSection($m[1], $m[2], $m[3], $m[4]);
            }, $template);
            $template = preg_replace_callback("/\{loop\s+($this->mVarRegexp)\s+($this->mVarRegexp)\}(.+?)\{\/loop\}/is", function ($m) {
                return $this->loopSection($m[1], 0, $m[2], $m[3]);
            }, $template);
		}
        return $template;
    }
    public function replaceIf($template)
    {
        return preg_replace_callback("/\{if\s+(.+?)\}/is", function ($m) {
            return $this->stripVtag('<?php if ('.$m[1].') { ?>');
        }, $template);//替换if标签
    }
    public function replaceInclude($template)
    {
        return preg_replace("/\{include\s+(.*?)\}/is", "<?php include \\1; ?>", $template);//替换include标签
    }
    public function replaceTemplate($template)
    {
        return preg_replace("/\{template\s+([\w\/\.:]+?)\}/is", "<?php include template('\\1'); ?>", $template);//替换template标签
    }
    public function replaceElse($template)
    {
        return preg_replace("/\{else\}/is", "<?php } else { ?>", $template);//替换else标签
    }
    public function replaceIfEnd($template)
    {
		$template = preg_replace("/\{\/if\}/is", "<?php } ?>", $template);//替换/if标签
        return $template;
    }
    public function replaceForEnd($template)
    {
		$template = preg_replace("/\{\/for\}/is", "<?php } ?>", $template);//替换/for标签
        return $template;
    }

    public function replace2dArray($template)
    {
        $template = preg_replace("/(\\\$[a-zA-Z_]\w+\[)([a-zA-Z_]\w+)\]/i", "\\1'\\2']", $template);//将二维数组替换成带单引号的标准模式
        return $template;
    }

	/**
	*  解析模板标签
	*
	* @param string $template ：模板源文件内容
	* @return string
	*/
	function parse($template)
	{
        $template = $this->replaceConstant($this->replaceVar($this->delComments($template)));
        $template = $this->replaceIf($this->replaceLoop($this->replaceElseif($this->replaceFor($this->replacePHP($template)))));
        $template = $this->replaceTemplate($this->replaceInclude($template));
        $template = $this->replaceForEnd($this->replaceIfEnd($this->replaceElse($template)));
		return $template;
	}

	/**
	 * 正则表达式匹配替换
	 *
	 * @param string $s ：
	 * @return string
	 */
	function stripVtag($s)
	{
        return preg_replace("/$this->mVtagRegexp/is", "\\1", str_replace("\\\"", '"', $s));
	}
	/*
	function stripBlock($bk_id)
	{
		global $g_db, $SETTING;
		if (!is_numeric($bk_id))
		{
			return '';
		}
		$sql		= "select * from {$SETTING['db']['prefix']}block where bk_id = $bk_id";
		$bk			= $g_db->getAll($sql);
		if ($bk)
		{
			return replaceAttach($bk[0]['bk_content'], 'block', $bk_id);
		}
	}*/

	function stripTagQuotes($expr)
	{
		$expr = preg_replace("/\<\?php echo (\\\$.+?);\?\>/s", "{\\1}", $expr);
		$expr = str_replace("\\\"", "\"", preg_replace("/\[\'([a-zA-Z0-9_\-\.\x7f-\xff]+)\'\]/s", "[\\1]", $expr));
		return $expr;
	}
	/**
	* 将模板中的块替换成BLOCK函数
	*
	* @param string $blockname ：
	* @param string $parameter ：
	* @return string

	function stripBlock($parameter)
	{
		return $this->stripTagQuotes("<?php YK_block(\"$parameter\"); ?>");
	}
	*/

	/**
	* 替换模板中的LOOP循环
	*
	* @param string $arr ：
	* @param string $k ：
	* @param string $v ：
	* @param string $statement ：
	* @return string
	*/
	function loopSection($arr, $k, $v, $statement)
	{
		$arr = $this->stripVtag($arr);
		$k = $this->stripVtag($k);
		$v = $this->stripVtag($v);
		$statement = str_replace("\\\"", '"', $statement);
		return $k ? "<?php foreach ((array)$arr as $k=>$v) {?>$statement<?php } ?>" : "<?php foreach ((array)$arr as $v) {?>$statement<?php } ?>";
	}
}
?>
