<?php

 class AdTest extends \Test\BaseTestCase {
    public function testLists()
    {
        $params['status'] = 1;
        $params['mark'] = 1;
        $params['isInDate'] = 1;

        $res=$this->curlGet('common/ad/lists',$params);
        $this->assertEquals(0,$res->error_code);
    }
    public function testStartAppAd() {

        $params['status'] = 1;
        $params['mark'] = 2;
        $params['isInDate'] = 1;

        $res=$this->curlGet('common/ad/startAppAd',$params);
        $this->assertEquals(0,$res->error_code);
    }

    /**
     * 首页弹出框
     */
    public function testDialog() {
        $params['uid'] = 1;
        $params['status'] = 1;
        $params['mark'] = 4;
        $params['isInDate'] = 1;
        $res=$this->curlGet('common/ad/dialog',$params);
        $this->assertEquals(0,$res->error_code);
    }
}
