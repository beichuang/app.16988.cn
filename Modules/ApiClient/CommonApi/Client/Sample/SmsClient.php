<?php
namespace ApiClient\CommonApi\Client\Sample;

/**
 *
 *
 *
 * 模板编号说明文档
 *
 * @link http://192.168.1.200/%E5%BC%80%E5%8F%91%E7%BB%84:%E9%98%BF%E9%87%8C%E4%BA%91%E7%9F%AD%E4%BF%A1%E6%A8%A1%E6%9D%BF
 */
class SmsClient extends ApiClient
{

    private $action = 'message/sms/singleSendSms';

    public function __construct($appId, $appSecret, $serverUrl = '', $serverIp = '', $timeOut = 3)
    {
        parent::__construct($appId, $appSecret, $serverUrl, $serverIp, $timeOut);
        return $this;
    }

    /**
     *
     * @param string $templateType
     *            模板编号 参考：
     * @link http://192.168.1.200/%E5%BC%80%E5%8F%91%E7%BB%84:%E9%98%BF%E9%87%8C%E4%BA%91%E7%9F%AD%E4%BF%A1%E6%A8%A1%E6%9D%BF
     * @param string $recNum，手机号，多个用逗号隔开            
     * @param array $vars
     *            模板中变量的值，默认array()
     * @param string $signName
     *            短信签名，默认【保险岛】
     * @throws \Exception
     * @return Ambigous <\ApiClient\CommonApi\Client\Sample\mixed, boolean, mixed, string>
     */
    public function sendSMS($templateType, $recNum, $vars = array(), $signName = '保险岛',$recNumCheck='N')
    {
        if (! $recNum) {
            throw new \Exception("手机号码必须！");
        }
        $errorNums=array();
        $successNums=array();
        list($errorNums,$successNums)=$this->checkMobileNum($recNum);
        if(!empty($errorNums)){
            if(strtoupper($recNumCheck)!='N'){
                throw new \Exception("[".implode(',', $errorNums)."]不是合法的手机号！");
            }else {
                $recNum=implode(',', $successNums);
            }
        }
        $http_params = $this->buildRequestParams($templateType, $recNum, $vars, $signName);
        $http_params['recNumCheck']=$recNumCheck;
        $res = $this->doRequest($this->action, $http_params);
        return $res;
    }

    private function buildRequestParams($templateType, $recNum, $sms_params, $signName = "保险岛")
    {
        $params = array();
        $params['templateType'] = $templateType;
        $params['recNum'] = $recNum;
        $params['paramString'] = count($sms_params) > 0 ? json_encode($sms_params) : '{}';
        $params['signName'] = $signName;
        return $params;
    }

    private function checkMobileNum($recNums)
    {
        $pattern="/^1\d{10}$/";
        $recNumsArr=explode(",", $recNums);
        $errorNums=array();
        $successNums=array();
        foreach ($recNumsArr as $i=>$recNum){
            if(!preg_match($pattern, $recNum)){
                $errorNums[]=$recNum;
            }else{
                $successNums[]=$recNum;
            }
        }
        return array($errorNums,$successNums);
    }
}
/**
 * 例子：
 * 
$smsClient=new SmsClient();
$res=$smsClient->sendSMS('BXD_BASE_VALIDATE','18638781763', array(
                        'product'=>'bxd365','type'=>'注册','smscode'=>'564521','time'=>'15'
                ),"保险岛");
var_dump($res);

*/