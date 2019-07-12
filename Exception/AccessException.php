<?php
namespace Exception;

/**
 * 访问异常
 * ERROR_TYPE=1
 *
 * @author Administrator
 *        
 */
class AccessException extends \Exception
{

    const ERROR_TYPE = 1;
    
    // 资源不存在
    const CODE_RESOURCE_NOT_EXISTS = 1;
    
    // 未登录
    const CODE_USER_NOT_LOGIN = 2;
    
    // 版本过低
    const CODE_APP_VERSION_TOO_LOW = 3;
    
    // 签名校验失败
    const CODE_SIGN_CHECK_FAIL = 4;
}
