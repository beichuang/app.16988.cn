<?php
/**
 * 发现 
 * @author Administrator
 *
 */

 class MarketTest extends \Test\BaseTestCase
{
    public function testlists()
    {
        $params['provinceCode']='10011';
        $params['cityCode']='10039';
//        $params['areaCode']='';
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->curlGet('Found/Market/lists',$params);
        $this->assertEquals(0,$res->error_code);

        return;
    }
}