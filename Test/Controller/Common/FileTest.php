<?php
class FileTest extends \Test\BaseTestCase
{
    public function testuploadImagesOld()
    {
        $params['thumb']='';
        $params['imageType']='';
        $r=$this->curlGet('common/file/uploadImagesOld',$params);
        $this->assertEquals(0,$r->error_code);
    }

    public function testuploadImages()
    {
        $params['imageType']='';
        $r=$this->curlGet('common/file/uploadImages',$params);
        $this->assertEquals(0,$r->error_code);
    }
}