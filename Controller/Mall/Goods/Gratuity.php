<?php
/**
 * 商品打赏
 *
 */
namespace Controller\Mall\Goods;

use Lib\Base\BaseController;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Gratuity extends BaseController
{

    private $gratuityModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->gratuityModel = new \Model\Pay\Gratuity();
    }

    /**
     * 分页查询
     *
     * @throws ModelException
     */
    public function lists()
    {
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 5);
        $params = array();

        $params['g_id'] = app()->request()->params('g_id');

        if (! $params['g_id']) {
            throw new ParamsInvalidException("缺少参数:g_id");
        }

        list ($rows, $totalCount) = $this->gratuityModel->lists($params, $page, $pageSize);
        if ($rows && is_array($rows) && ! empty($rows)) {
            foreach ($rows as &$row) {
                $time = strtotime($row['gl_time']);
                $row['displayTime'] = date_format_to_display($time);
            }
            $userLib = new \Lib\User\User();
            $rows = $userLib->extendUserInfos2Array($rows, 'u_id',
                array(
                    'u_nickname' => 'u_nickname',
                    'u_realname' => 'u_realname',
                    'u_avatar' => 'u_avatar',
                ));
        }
        $this->responseJSON(array(
            'rows' => $rows,
            'totalCount' => $totalCount
        ));
    }




}
