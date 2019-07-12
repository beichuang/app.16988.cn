<?php

//快递鸟查询订单信息

namespace kdNiao;

class kdNiao
{

    private $eBusinessID = '1295236';
    private $appKey= '3a0bfe73-5fb2-448b-87f7-2bd0a8d1082f';
    private $reqURL = 'http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';

    /**
     * 快递查询
     */
    public function orderTracesSubByJson($LogisticCode,$ShipperCode)
    {
        $requestData="{'ShipperCode':'".$ShipperCode."',".
        	   "'LogisticCode':'".$LogisticCode."'}";
        
        $datas = array(
            'EBusinessID' => $this->eBusinessID,
            'RequestType' => '1002',
            'RequestData' => urlencode($requestData) ,
            'DataType' => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, $this->appKey);
        
        $result=$this->sendPost($this->reqURL, $datas);
        
        //根据公司业务处理返回的信息......
        
        return $result;
    }

    /**
     * 根据快递单号识别出可能的快递信息
     * @param $logisticCode
     * @return string
     */
    public function getExpressData($logisticCode)
    {
        //请求业务参数
        $requestData = json_encode(['LogisticCode' => $logisticCode]);
        $postData['RequestData'] = $requestData;
        $postData['EBusinessID'] = $this->eBusinessID;
        $postData['RequestType'] = '2002';
        $postData['DataType'] = '2';
        $postData['DataSign'] = $this->encrypt($requestData, $this->appKey);

        return $this->sendPost($this->reqURL, $postData);
    }

    /**
     *  post提交数据
     * @param  string $url 请求Url
     * @param  array $datas 提交的数据
     * @return string
     */
    public function sendPost($url, $datas) {
        $temps = array();
        foreach ($datas as $key => $value) {
            $temps[] = sprintf('%s=%s', $key, $value);
        }
        $post_data = implode('&', $temps);
        $url_info = parse_url($url);
        if(empty($url_info['port']))
        {
            $url_info['port']=80;
        }
        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
        $httpheader.= "Host:" . $url_info['host'] . "\r\n";
        $httpheader.= "Content-Type:application/x-www-form-urlencoded\r\n";
        $httpheader.= "Content-Length:" . strlen($post_data) . "\r\n";
        $httpheader.= "Connection:close\r\n\r\n";
        $httpheader.= $post_data;
        $fd = fsockopen($url_info['host'], $url_info['port']);
        fwrite($fd, $httpheader);
        $gets = "";
        $headerFlag = true;
        while (!feof($fd)) {
            if (($header = @fgets($fd)) && ($header == "\r\n" || $header == "\n")) {
                break;
            }
        }
        while (!feof($fd)) {
            $gets.= fread($fd, 128);
        }
        fclose($fd);

        return $gets;
    }
    
    /**
     * 电商Sign签名生成
     * @param data string 内容
     * @param appkey string Appkey
     * @return DataSign string 签名
     */
    public function encrypt($data, $appkey) {
        return urlencode(base64_encode(md5($data.$appkey)));
    }
}
