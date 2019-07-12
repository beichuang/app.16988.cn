<?php

 class ResourceTest extends \Test\BaseTestCase {


    public function testhtml()
    {
        $res=$this->curlGet('html/download.html');
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertStringStartsWith('<!doctype html>', $res);
        $this->assertRegExp('/艺术寻宝/', $res);
    }
}
