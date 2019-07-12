<?php
/**
 * 阿里云oss文件
 * @author Administrator
 *
 */
namespace Model\Common;

use Lib\Base\BaseModel;

class OssFiles extends BaseModel
{
    protected $table = 'oss_files';
    protected $id = 'of_id';

    public function save($data)
    {
        $this->insert($data);
    }
}
