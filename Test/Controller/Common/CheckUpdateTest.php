<?php


/**
 * 检查版本等
 *
 * @author yangzongxun
 *        
 */
class CheckUpdateTest extends \Test\BaseTestCase
{

    /**
     * 检查Android，app版本
     *
     * @return string
     */
    public function testandroid()
    {
        $res=$this->curlGet('common/checkupdate/android');
        $this->assertEquals(0,$res->error_code);
    }

    /**
     * 检查ios,app版本
     *
     * @return string
     */
    public function testios()
    {
        $res=$this->curlGet('common/checkupdate/ios');
        $this->assertEquals(0,$res->error_code);
    }
}
