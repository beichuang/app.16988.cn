<?php

 class RegionTest extends \Test\BaseTestCase
{

    /**
     * 查询地区信息
     */
    public function testget()
    {
        $params['with_keys']=1;
        $params['showTree']=1;
        $r=$this->curlGet('common/region/get',$params);
        $this->assertEquals(0,$r->error_code);
    }

    public function testgetList()
    {
        $r=$this->curlGet('common/region/getList');
        $this->assertEquals(0,$r->error_code);
    }

    /**
     * 查询省份code
     */
    public function testgetProvinces()
    {
        $params['province']='河南';
        $r=$this->curlGet('common/region/getProvinces',$params);
        $this->assertEquals(0,$r->error_code);
    }
    
    /**
     * 查询省和市code
     */
    public function testgetProvinceCityCode()
    {
        $params['province']='河南';
        $params['city']='郑州';
        $params['area']='高新区';
        $r=$this->curlGet('common/region/getProvinceCityCode',$params);
        $this->assertEquals(0,$r->error_code);
    }
    
    public function testcurrentIp()
    {
        $r = $this->curlGet('common/region/currentIp');
        var_dump($r);
        $this->assertEquals(0, $r->error_code);
    }
}
