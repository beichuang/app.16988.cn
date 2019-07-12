<?php

/**
 * 发现
 * @author Administrator
 *
 */

 class NearByTest extends \Test\BaseTestCase
{
    public function testuserFoundAll()
    {
        $params['point_x']='1';
        $params['point_y']='1';
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->withLogin()->curlPost('found/nearby/userFoundAll',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
    }

    /**
     * 推荐机构
     */
    public function testrecommendBodies()
    {
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->withLogin()->curlPost('found/nearby/recommendBodies',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        $this->assertGreaterThanOrEqual(0,$res->data->count);
    }

    /**
     * 推荐艺术家
     */
    public function testrecommendArtist()
    {
        $params['is_own_show']=1;
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->withLogin()->curlPost('found/nearby/recommendArtist',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
        $this->assertGreaterThanOrEqual(0,$res->data->count);
    }

    /**
     * 新加入艺术家
     */
    public function testnewArtist()
    {
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->withLogin()->curlPost('found/nearby/newArtist',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
    }



    /**
     * 新增附近玩友
     *
     * @throws ModelException
     */
    public function testuserNearList()
    {
        $params['type']='0';
        $params['point_x']='1';
        $params['point_y']='10';
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->withLogin()->curlPost('found/nearby/userNearList',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
    }
   /**
     * 新增附近商品
     *
     * @throws ModelException
     */
    public function testgoodsList()
    {
        $params['provinceCode']='10011';
        $params['cityCode']='10039';
//        $params['areaCode']='0';
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->withLogin()->curlPost('found/nearby/goodsList',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
    }

    /**
     * 新增附近名家
     *
     * @throws ModelException
     */
    public function testcelebrity()
    {
        $params['provinceCode']='10011';
        $params['cityCode']='10039';
//        $params['areaCode']='0';
        $params['page']='1';
        $params['pageSize']='10';
        $res=$this->withLogin()->curlPost('found/nearby/celebrity',$params);
        $this->assertEquals(200,$this->curl->httpStatusCode);
        $this->assertEquals(0,$res->error_code);
    }

}
