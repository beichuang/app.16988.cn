<?php

//实名认证+银行卡信息验证

namespace realName;

class realName
{
    /**
     * 实名认证+银行卡信息验证
     */
    public function authentication($realnames,$IDNo,$uce_bankCardNo,$phone)
    {
        $info = '';
        
        $host = "http://lundroid.market.alicloudapi.com";
        $path = "/lianzhuo/verifi";
        $method = "GET";
        $appcode = "a23c992156be4b5084298391fc3addb9";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "acct_name=".urlencode($realnames)."&acct_pan=".$uce_bankCardNo."&cert_id=".$IDNo."&phone_num=".$phone;
        
        $bodys = "";
        $url = $host . $path . "?" . $querys;
    
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($curl, CURLOPT_HEADER, true);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        $info = curl_exec($curl);
       
        //$info = '{"data":{},"resp":{"code":0,"desc":"ok"}}';
        if ($info)
        {
            $info_arr = json_decode($info,true);
        }
        else{
            $info_arr['resp'] = ['code'=>10000,'desc'=>'认证失败'];
        }
        //error返回:{"data":{},"resp":{"code":5,"desc":"持卡人认证失败"}}
        //suc：{"resp":{"code":0,"desc":"OK"},"data":{},}
        
        //curl_close($curl);
        return $info_arr['resp'];
    }

}
