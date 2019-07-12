<?php
/**
 * 头条分类
 * @author Administrator
 *
 */
namespace Controller\News;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Category extends BaseController
{

    private $newsCategory = null;

    public function __construct()
    {
        parent::__construct();
        $this->newsCategory = new \Model\News\NewsCategory();
    }


    /**
     * 查询分类
     */
    public function lists()
    {
        $params = app()->request()->params();
        $page = app()->request()->params('page', 1);
        $pageSize = app()->request()->params('pageSize', 20);

        $params['parentId'] = 0;
        $params['isShow'] = 1;
        list($resMall['list'],$resMall['count']) = $this->newsCategory->lists($params, $page, $pageSize);

        $this->responseJSON($resMall);
    }

    public function search()
    {
        // $params = app()->request()->params();
        $params['id'] = 0;
        $resMall = $this->goodsCategoryLib->lists($params);
        $searchList['item_size'] = $this->searchSize();
        $searchList['item_category'] = $resMall['list'];
        // var_dump($resMall['list']);exit;
        $this->responseJSON($searchList);
    }

}
