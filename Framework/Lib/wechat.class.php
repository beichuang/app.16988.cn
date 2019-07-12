<?php
class Wechat
{
	private $mTOKEN = '';
    public $ValidSate = '';
    public $Sign = '';
    public $Cl_id = '';

	function __construct(){}

	public function setToken($token)
	{
		$this->mToken = $token;
	}
    public function valid()
    {
        $echoStr = $_GET['echostr'];
        //有效签名，选项
        if ($this->checkSignature())
        {
            $this->ValidSate = 'ok';
            echo $echoStr;
            die;
        }
    }
    private function checkSignature()
    {
        $signature = $_GET['signature'];
        $timestamp = $_GET['timestamp'];
        $nonce = $_GET['nonce'];
        $tmpArr = array($this->mToken, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    public function responseMsg()
    {
        //使用 get 亦或 post 数据，取决于不同环境
        $postStr = @file_get_contents('php://input');
        //获取 post 数据
        if (!empty($postStr))
        {
            //用 SimpleXML 解析 post 过来的 XML 数据
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $type = trim($postObj->MsgType);
            //判断数据类型
            switch ($type)
            {
                case 'text':
                    $resultStr = $this->receiveText($postObj);
                    break;
                case 'event':
                    $resultStr = $this->receiveEvent($postObj);
                    break;
                case 'image':
                    $resultStr = $this->receiveImage($postObj);
                    break;
                default:
                    $resultStr = '未知消息类型: ' . $type;
                    break;
            }
            echo $resultStr;
        }
        else
        {
            echo '';
            die;
        }
    }
    private function receiveText($object)
    {
        $funcFlag = 0;
        $keyword = trim($object->Content);
        //获取消息内容
        $resultStr = '';
        $contentStr = '';
        //返回数据
        //设置回复关键词21
        if ($keyword == 'test')
        {
            //$contentStr = '';
            //$resultStr = $this->transmitText($object, $contentStr, $funcFlag);
			//$resultStr = $this->transmitNews($object, 'title', '点击查看完整内容', 'http://qd.hnek.net/skins/image/pic6.jpg', 'http://qd.hnek.net/news1.htm');
            //return $resultStr;
        }
        else
        {
            //$contentStr = '你好。';
            //返回消息内容
            //$resultStr = $this->transmitText($object, $contentStr, $funcFlag);
            return $resultStr;
        }
    }
    private function receiveEvent($object)
    {
        $contentStr = '';
        switch ($object->Event)
        {
            case 'subscribe':
                //关注後自动推送消息
                //$contentStr = '感谢您的关注，<a href="http://www.zzbaishun.com/qd/sign.php">点这里进行签到</a>。';
                if ($object->EventKey)
                {
                	$appid	= $this->getuserinfo($object);
                	//$resultStr = $this->transmitNews($object, '欢迎您', '请点击', 'http://qd.hnek.net/skins/image/pic2.jpg', 'http://qd.hnek.net/sign.php?userid='.$appid);
                    $resultStr = $this->transmitText('欢迎您', '欢迎您');
                }
                break;
            case 'SCAN':
                //关注後自动推送消息
                //$contentStr = '感谢您的关注，<a href="http://www.zzbaishun.com/qd/sign.php">点这里进行签到</a>。';
                if ($object->EventKey)
                {
               		$appid	= $this->getuserinfo($object);
                	//$resultStr = $this->transmitNews($object, '欢迎您', '请点击', 'http://qd.hnek.net/skins/image/pic2.jpg', 'http://qd.hnek.net/sign.php?userid='.$appid);
                    $resultStr = $this->transmitText('欢迎您', '欢迎您');
                }
                break;
        }
        return $resultStr;
    }
    private function receiveImage($object)
    {
	    global $g_db, $SETTING, $g_timestamp;
        $contentStr	= $object->PicUrl;
        $resultStr	= $this->transmitText($object, "图片上传成功，正在准备打印，请稍候。<a href=\"{$contentStr}\">点这里查看图片</a>");
	    //保存文件
	    $setarr = array(
		    'wf_file'				        => $contentStr,
		    'wf_state'			            => 0,
		    //'wc_id'			                => $this->Cl_id,
		    'wf_from'			            => $object->FromUserName,
		    'wf_receiveTime'				=> date('Y-m-d H:i:s', $g_timestamp),
		    'wf_printTime'					=> '',
            'wf_sign'                       => $this->Sign,
            'cl_id'                         => $this->Cl_id
	    );

	    $sql = ykInserttable("{$SETTING['db']['prefix']}weixin_file", $setarr);
	    $g_db->query($sql);

        return $resultStr;
    }
    
    private function getuserinfo($object)
    {
    	global $access_token, $g_db, $g_timestamp, $SETTING;
		$url		= "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$object->FromUserName}&lang=zh_CN";
		$content	= json_decode(curlPost($url, array(), 30, false));
		
		//查找是否已经存在
		$sql			= "select * from {$SETTING['dbpre']['base']}scanperson where sp_openid = '{$object->FromUserName}'";
		$scanperson		= $g_db->getAll($sql);
		if (count($scanperson) > 0)
		{
			return $object->FromUserName;
		}
		
		$setarr = array(
			'sp_openid'			=> $object->FromUserName,
			'sp_headimgurl'		=> $content->headimgurl,
			'sp_nickname'		=> $content->nickname,
			'sp_subscribe_time'	=> $content->subscribe_time,
			'sp_scantime'		=> $g_timestamp
		);
		
		if (true)
		{
			$setarr	= sqliteEncode($setarr);
		}
		
		$sql = fwInserttable("{$SETTING['dbpre']['base']}scanperson", $setarr);
		$g_db->query($sql);
		
		return $object->FromUserName;
    }
	//发送文字消息
    private function transmitText($object, $content, $flag = 0)
    {
        //返回文本消息模板
        $textTpl = '<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[text]]></MsgType>
                <Content><![CDATA[%s]]></Content>
                <FuncFlag>%d</FuncFlag>
                </xml>';
        //格式化消息模板
        $resultStr = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content, $flag);
        return $resultStr;
    }
	//发送图文消息
    private function transmitNews($object, $title, $content, $picurl, $url, $flag = 0)
    {
        //返回文本消息模板
        $textTpl = '<xml>
				<ToUserName><![CDATA[%s]]></ToUserName>
				<FromUserName><![CDATA[%s]]></FromUserName>
				<CreateTime>%s</CreateTime>
				<MsgType><![CDATA[news]]></MsgType>
				<ArticleCount>1</ArticleCount>
				<Articles>
				<item>
				<Title><![CDATA[%s]]></Title> 
				<Description><![CDATA[%s]]></Description>
				<PicUrl><![CDATA[%s]]></PicUrl>
				<Url><![CDATA[%s]]></Url>
				<FuncFlag>%d</FuncFlag>
				</item>
				</Articles>
				</xml>';
        //格式化消息模板
        $resultStr = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $title, $content, $picurl, $url, $flag);
        return $resultStr;
    }
}
?>