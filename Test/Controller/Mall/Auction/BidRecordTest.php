<?php

/**
 * 拍品出价记录
 * @author Administrator
 *
 */

 class BidRecordTest extends \Test\BaseTestCase
{

    private $auctionLib = null;



    /**
     * 新增出价记录
     *
     * @throws ModelException
     */
    public function testadd()
    {

        $params['a_id'] = '1';
        $params['price'] = '100';
        $res = $this->withLogin()->curlPost('mall/auction/bidRecord/add', $params);
        $this->assertEquals(200, $this->curl->httpStatusCode);
//        $this->assertEquals(0, $res->error_code);
        return;
    }

    /**
     * 查询出价记录
     */
    public function testgetList()
    {
        $params['a_id'] = '1';
        $res = $this->withLogin()->curlPost('mall/auction/bidRecord/getList', $params);
        $this->assertEquals(200, $this->curl->httpStatusCode);
        $this->assertEquals(0, $res->error_code);
//        var_dump($res);
//        $this->assertGreaterThanOrEqual(0, $res->data->currentData->abr_price);
        return;
    }
}
