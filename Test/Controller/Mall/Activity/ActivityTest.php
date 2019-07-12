<?php

 class ActivityTest extends \Test\BaseTestCase {



    /**
     * 活动列表
     */
    public function testlists() {

        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->curlPost('mall/activity/activity/lists',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        return;
    }

    /**
     * 活动详情
     */
    public function testdetails()
    {
        $params['aid'] = '1';
        $res = $this->curlPost('mall/activity/activity/details', $params);
        $this->assertEquals(200, $this->curl->httpStatusCode);
        $this->assertEquals(0, $res->error_code);
        return;
    }
}
