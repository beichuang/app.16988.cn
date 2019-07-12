<?php
/**
 * 专题
 * @author Administrator
 *
 */

namespace Model\Special;

use Lib\Base\BaseModel;

class SpecialNew extends BaseModel
{

    protected $table = 'campaign_special';

    protected $id = 'cs_id';

    public function getOneLine($condition)
    {
        $where = $data = array();
        foreach ($condition as $key => $value) {
            $where[] = " $key = :$key ";
            $data[$key] = $value;
        }
        $where = implode(' and ', $where);
        return $this->one($where, $data);;
    }


}
