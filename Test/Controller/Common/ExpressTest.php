<?php

 class ExpressTest extends \Test\BaseTestCase
{
    public function testcompanyList()
    {
        $r=$this->curlGet('common/express/companyList');
        $this->assertEquals(0,$r->error_code);
    }
}
