<?php
namespace Test;

use PHPUnit\Framework\TestCase;
/**
 * Array测试用例
 * Class ArraysTest
 */
class BaseTestCase extends TestCase
{
    public $host='dev-app.16988.cn';
    public $basUrl='http://192.168.56.102/';
    public $u_phone='18638781763';
    public $u_pwd='y123456';
    /**
     * @var \Curl\Curl()
     */
    public $curl=null;

    public function withLogin()
    {
        $cookieFile=__DIR__.'/loginCookie.txt';
        if(!file_exists($cookieFile)){
            $params['phone']=$this->u_phone;
            $params['password']=$this->u_pwd;
            $res=$this->curlPost('user/common/login',$params);
            $this->assertEquals(0,$res->error_code);
            file_put_contents($cookieFile,$this->curl->responseHeaders['Set-Cookie'],FILE_APPEND);
        }
        $this->curl->setCookieString(file_get_contents($cookieFile));
        return $this;
    }

    protected function withWxClient(){
        $this->curl->setHeader('user-agent','MicroMessenger');
        return $this;
    }
    protected function curlGet($uri,$params=[])
    {
        return $this->curl->get($this->basUrl.$uri,$params);
    }
    protected function curlPost($uri,$params=[])
    {
        return $this->curl->post($this->basUrl.$uri,$params);
    }

    protected function setUp()
    {
        $this->curl=new \Curl\Curl();
        $this->curl->setHeader('Host',$this->host);
    }
    protected function tearDown()
    {

    }

}