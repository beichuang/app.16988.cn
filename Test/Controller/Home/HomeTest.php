<?php

/**
 * 首页
 * @author Administrator
 *
 */

 class HomeTest extends \Test\BaseTestCase {
    public function testhomeAll() {
        $res=$this->curlPost('home/home/homeAll');
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        return;
    }

    /**
     * 总的列表页入口
     */
    public function testgetGoodLists() {
        $params['tabType']='1';
        $res=$this->curlPost('home/home/getGoodLists',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        $params['tabType']='2';
        $res=$this->curlPost('home/home/getGoodLists',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        $params['tabType']='3';
        $res=$this->curlPost('home/home/getGoodLists',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        $params['tabType']='4';
        $res=$this->curlPost('home/home/getGoodLists',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        return;
    }
}
