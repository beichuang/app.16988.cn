<?php
namespace Middleware;

use Exception;
use Exception\ServiceException;

/**
 * 异常处理类，中间件
 */
class ExceptionHandler extends \Framework\Middleware
{

    /**
     * (non-PHPdoc)
     *
     * @see \Framework\Middleware::call()
     */
    public function call()
    {
        register_shutdown_function(function(){
            $error=error_get_last();
            if ($error && is_array($error)){
                wlog($error,'fatal-shutdown-error');
            }
        });
        $app = $this->app;
        try {
            $this->next->call();
            $this->log('access');
        } catch (\Exception $e) {
            $contents = $app->response()->getBody();
            $error_type = $this->getErrorType($e);
            $error_code = $e->getCode();
            if (! $error_code) {
                $error_code = 1;
            }
            $res = array(
                "error_code" => $error_code,
                "error_type" => $error_type,
                "error_msg" => $this->getErrorMsg($e),
                "detail" => $contents
            );
            if ($app->getMode() == 'development') {
                $res['error_file'] = $e->getFile();
                $res['error_line'] = $e->getLine();
                $res['error_tracing'] = $e->getTraceAsString();
            }
            $APPID_HTTP_PARAM_NAME = "app.http_param_name.appid";
            $appid = $this->app->request()->params(config($APPID_HTTP_PARAM_NAME), '');
            if (is_jsonp_request()) {
                jsonp_response($res);
            } else {
                $app->response()->withJson($res);
            }
            $this->log($e,$error_type);
        }
    }

    private function getErrorMsg(\Exception $e){
        if($this->app->getMode() != 'development'){
            if(
                $e instanceof \Exception\ParamsInvalidException
                || $e instanceof \Exception\ServiceException
                ||$e instanceof \Exception\AccessException
            ){
                return $e->getMessage();
            }else{
                return '内部错误,请稍候再试';
            }
        }
        return $e->getMessage();
    }
    /**
     * 获取错误类型
     *
     * @param \Exception $e            
     * @return number
     */
    private function getErrorType(\Exception $e)
    {
        if ($e instanceof \Exception\AccessException) {
            return \Exception\AccessException::ERROR_TYPE;
        } else if ($e instanceof \Exception\ParamsInvalidException) {
            return \Exception\ParamsInvalidException::ERROR_TYPE;
        } else if ($e instanceof \Exception\ServiceException) {
            return \Exception\ServiceException::ERROR_TYPE;
        } else if ($e instanceof \Exception\ModelException || $e instanceof \PDOException) {
            return \Exception\ModelException::ERROR_TYPE;
        } else if ($e instanceof \Exception\InternalException) {
            return \Exception\InternalException::ERROR_TYPE;
        } else if ($e instanceof \Exception) {
            return 99;
        } else {
            return 100;
        }
    }

    /**
     * 记访问日志
     *
     * @param object $msg            
     */
    private function log($msg,$error_type=0)
    {
        $uri = $this->app->request->getPath();
        $requestParams = $this->app->request()->params();
        $requestHeader = $this->app->request()->headers();
        $responseBody = $this->app->response()->getBody();
        $responseStatus = $this->app->response()->getStatus();
        $responseHeader = $this->app->response()->headers();
        $accessInfo = array(
            'uri' => $uri,
            'requestParams' => $requestParams,
            'requestHeader' => $requestHeader,
            'responseBody' => $responseBody,
            'responseStatus' => $responseStatus,
            'responseHeader' => $responseHeader
        );
        $logFileSuffix = date('Y_m_d_H');
        if ($msg instanceof \Exception) {
            $accessInfo['msg'] = array(
                'errorMessage' => $msg->getMessage(),
                'errorFile' => $msg->getFile(),
                'errorLine' => $msg->getLine(),
                'errorCode' => $msg->getCode(),
                'errorTracing' => $msg->getTraceAsString()
            );
            $errorLevel=\Framework\Log::ERROR;
            if(in_array($error_type, [
                \Exception\AccessException::ERROR_TYPE,
                \Exception\ParamsInvalidException::ERROR_TYPE,
                \Exception\ServiceException::ERROR_TYPE,
            ]));
            $errorLevel=\Framework\Log::INFO;
            wlog($accessInfo, 'api-error-' . $logFileSuffix, \Framework\Log::ERROR);
        } else {
            $accessInfo['msg'] = $msg;
            wlog($accessInfo, 'api-access-' . $logFileSuffix, \Framework\Log::INFO);
        }
    }
}
