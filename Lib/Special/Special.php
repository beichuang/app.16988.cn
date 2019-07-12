<?php
/**
 * 专题
 * @author Administrator
 *
 */

namespace Lib\Special;

use Exception\InternalException;
use Exception\ServiceException;
use Framework\Lib\Validation;
use Exception\ParamsInvalidException;
use Lib\Common\AppMessagePush;


class Special
{

    /**
     * 专题数据
     *
     * @throws ModelException
     */
    public function getOne($condition)
    {
        $params = [];

        if (isset($condition['cs_id']) && $condition['cs_id'] != '') {
            $params['cs_id'] = $condition['cs_id'];
        }

        $specialModel = new \Model\Special\Special();

        $getOne = $specialModel->getOneLine($params);

        return $getOne;
    }

    public function getInfo($condition)
    {
        $params = [];

        if (isset($condition['ss_id']) && $condition['ss_id'] != '') {
            $params['ss_id'] = $condition['ss_id'];
        }

        $specialModel = new \Model\Special\Special();

        $getOne = $specialModel->getOneLine($params);

        return $getOne;
    }

}
