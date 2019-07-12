<?php
namespace Lib\Pay;

class Pay
{
    /**
     * 处理第三方支付的回调通知
     * @param string $method
     */
    public function handlerThirdpartyPayNoticeRouter($method)
    {
        try{
            switch ($method) {
                case 'alipay-wap':
                    new \Rest\Pay\PayNotifyAliWap();
                    break;
                case 'alipay-app':
                    new \Rest\Pay\PayNotifyAliApp();
                    break;
                case 'wechat-app':
                    new \Rest\Pay\PayNotifyWechatApp();
                    break;
                case 'wechat-app8':
                    new \Rest\Pay\PayNotifyWechatApp(8);
                    break;
                case 'wechat-jsapi':
                    new \Rest\Pay\PayNotifyWechatJsapi(6);
                    break;
                case 'wechat-jsapi7':
                    new \Rest\Pay\PayNotifyWechatJsapi(7);
                    break;
                case 'wechat-jsapi12':
                    new \Rest\Pay\PayNotifyWechatJsapi(12);
                    break;
                case 'baidu-pay':
                    new \Rest\Pay\PayNotifyBaiduMiniPay();
                    break;
                case 'baidu-refund':
                    new \Rest\Pay\PayNotifyBaiduMiniRefund();
                    break;
                case 'baidu-refundAudit':
                    new \Rest\Pay\PayNotifyBaiduMiniRefundAudit();
                    break;
                default:
                    app()->halt(404);
            }
        }catch (\Exception $e){
            wlog([
                'Exception'=>[
                    'getMessage'=>$e->getMessage(),
                    'getCode'=>$e->getCode(),
                    'getFile'=>$e->getFile(),
                    'getLine'=>$e->getLine(),
                    'getTraceAsString'=>$e->getTraceAsString(),
                ],
            ],'thirdparty-pay-notify-Exception-'.$method,\Framework\Log::WARN);
        }

        wlog([
            'requestInfo' => $this->get_access_request_info(),
            'responseInfo' => $this->get_access_response_info(),
        ],'thirdparty-pay-notify-'.$method,\Framework\Log::WARN);
    }

    /**
     * 获取请求信息
     * @return array
     */
    function get_access_request_info()
    {
        return array(
            'host'=>app()->request()->getHostWithPort(),
            'path'=>app()->request()->getPathInfo(),
            'body'=>app()->request()->getBody(),
            'headers'=>app()->request()->headers(),
            'post'=>app()->request()->post(),
            'get'=>app()->request()->get(),
            'cookies'=>app()->request()->cookies(),
        );
    }

    /**
     * 获取响应信息
     * @return array
     */
    function get_access_response_info()
    {
        return array(
            'headers'=>app()->response()->headers(),
            'body'=>app()->response()->getBody(),
            'status'=>app()->response()->getStatus(),
        );
    }
}
