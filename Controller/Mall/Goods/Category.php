<?php
/**
 * 商品评论
 * @author Administrator
 *
 */
namespace Controller\Mall\Goods;

use Framework\Helper\FileHelper;
use Exception\ParamsInvalidException;
use Lib\Base\BaseController;
use Exception\ModelException;
use Lib\Mall\GoodsCategory;
use Rest\Mall\Facade\ItemManager;

class Category extends BaseController
{

    private $goodsCategoryLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsCategoryLib = new \Lib\Mall\GoodsCategory();
    }


    private function searchSize()
    {
        $size = [
            ['id'=>1, 'name' => '10cm以下'],
            ['id'=>2, 'name' => '10-20cm'],
            ['id'=>3, 'name' => '20-50cm'],
            ['id'=>4, 'name' => '50-70cm'],
            ['id'=>5, 'name' => '70-100cm'],
            ['id'=>6, 'name' => '100cm以上'],
            ];

        return $size;
    }


    /**
     * 新增或修改分类
     *
     * @throws ModelException public function post()
     *         {
     *         $params = app()->request()->params();
     *         $params['salesId'] = $this->uid;
     *         $resMall = $this->goodsCategoryLib->post($params);
     *         $this->responseJSON($resMall);
     *         }
     */

    /**
     * 查询分类
     */
    public function lists()
    {
        $params = app()->request()->params();
        $resMall = $this->goodsCategoryLib->lists($params);
        if ($resMall['list']){
            $goodsLib = new \Lib\Mall\Goods();

            foreach ($resMall['list'] as &$value) {
                $ids = array();
                $data['c_id'] = $value['c_id'];
                $data['c_name'] = "全部";

                $ids[] = $value['c_id'];
                foreach ($value['lists'] as $val) {
                    $ids[] = $val['c_id'];
                }
                $goods_params['categoryId'] = $ids;
                //分类下自营商品数量
                if (isset($params['isOwnShop']) && $params['isOwnShop'] == '1') {
                    $goods_params['is_own_shop'] = 1;
                }
                //分类下商城商品数量
                if (isset($params['mall_goods_status']) && $params['mall_goods_status'] == '1') {
                    $goods_params['mall_goods_status'] = 1;
                }
                $goods_params['pageSize'] = 1;
                $goods_lists = $goodsLib->lists($goods_params);
                $value['totalCount'] = $goods_lists['count'];

                array_unshift($value['lists'], $data);
            }
        }

        $all['c_id'] = intval($params['id'])?intval($params['id']):0;
        $all['c_name'] = "全部";
        if($all['c_id']){
            array_unshift($resMall['list'], $all);
        }
        $this->responseJSON($resMall);
    }

    public function search()
    {
        // $params = app()->request()->params();
        $params['id'] = 0;
        $resMall = $this->goodsCategoryLib->lists($params);

        //分类是否显示商品参数
        if(!empty($resMall['list'])) {
            foreach ($resMall['list'] as &$firstLevelCategoryItem) {
                if (!empty($firstLevelCategoryItem['lists'])) {
                    foreach ($firstLevelCategoryItem['lists'] as &$categoryItem) {
                        if (in_array($categoryItem['c_id'], GoodsCategory::SHOW_GOODS_ATTR_IDS)
                            || (!empty($categoryItem['c_parentId']) && in_array($categoryItem['c_parentId'], GoodsCategory::SHOW_GOODS_ATTR_IDS))
                        ) {
                            $categoryItem['isShowGoodsAttr'] = 1;
                        } else {
                            $categoryItem['isShowGoodsAttr'] = 0;
                        }
                    }
                }
            }
        }

        $searchList['item_size'] = $this->searchSize();
        $searchList['item_category'] = $resMall['list'];
        // var_dump($resMall['list']);exit;
        $this->responseJSON($searchList);
    }

    /**
     * 寻宝页面的分类列表
     */
    public function treasureCategory(){
        //推荐分类[书法,国画,油画,陶瓷,全部]
        $recommend_categories = [20, 11, 31, 37, 0];
        $params = app()->request()->params();
        $params['id'] = app()->request()->params('id',0);
        $params['reSort'] = 1;  //1=是寻宝页面的分类列表
        $goodsLib = new \Lib\Mall\Goods();
        $resMall = $this->goodsCategoryLib->lists($params);
        $allCount=0;
        if ($resMall['list']){
            foreach ($resMall['list'] as &$value){
                $ids = array();

                $ids[] = $value['c_id'];
                foreach($value['lists'] as $val){
                    $ids[] = $val['c_id'];
                }
                unset($value['lists']);

                $goods_params['categoryId'] = $ids;
                $value['totalCount'] = $value['goods_own_onsale_count'];
                $allCount+=$value['totalCount'];
                //是否为推荐分类
                $value['isRecommend'] = in_array($value['c_id'], $recommend_categories);
            }
        }

        $all['c_id'] = 0;
        $all['c_name'] = "全部";
        $all['c_image'] = 'http:'.config('app.CDN.BASE_URL_RES').'/html/images/categoryAll.png';
        $all['totalCount'] = $allCount;
        //是否为推荐分类
        $all['isRecommend'] = in_array($all['c_id'], $recommend_categories);
        array_push($resMall['list'], $all);

        $this->responseJSON($resMall);
    }

    public function getOwnShopCategorys()
    {
        $data = [];
        //获取所有分类
        $getCategorySql = 'select `c_id`, `c_parentId`,`c_idPath`,`c_path`, `c_name` from `category` WHERE c_isDel=0 AND c_isShow=1';
        $categoryData = app('mysqlbxd_mall_common')->select($getCategorySql);
        if ($categoryData) {
            $categories = [];
            $categoryIds = array_column($categoryData, 'c_id');
            $getGoodsSql = 'select `g_categoryId` from `goods` WHERE `g_stock`>0 and `is_own_shop`=1 and `g_status`=3';
            $getGoodsSql .= ' AND FIND_IN_SET(g_categoryId,:categoryIds) GROUP BY `g_categoryId`';
            $hasGoodsCategoryData = app('mysqlbxd_mall_user')->select($getGoodsSql, [':categoryIds' => implode(',', $categoryIds)]);
            $hasGoodsCategoryIds = array_column($hasGoodsCategoryData, 'g_categoryId');

            //添加一级分类
            foreach ($categoryData as $categoryItem) {
                if (empty($categoryItem['c_parentId'])) {
                    $categories[$categoryItem['c_id']] = [
                        'c_id' => $categoryItem['c_id'],
                        'c_parentId' => $categoryItem['c_parentId'],
                        'c_name' => $categoryItem['c_name']
                    ];
                }
            }
            //添加二级分类
            foreach ($categoryData as $categoryItem) {
                if (!empty($categoryItem['c_parentId']) && !empty($categories[$categoryItem['c_parentId']]) && in_array($categoryItem['c_id'],
                        $hasGoodsCategoryIds)
                ) {
                    $categories[$categoryItem['c_parentId']]['lists'][] = [
                        'c_id' => $categoryItem['c_id'],
                        'c_parentId' => $categoryItem['c_parentId'],
                        'c_name' => $categoryItem['c_name'],
                    ];
                }
            }

            if ($categories) {
                foreach ($categories as $item) {
                    if (isset($item['lists'])) {
                        $data[] = $item;
                    }
                }
            }
            $this->responseJSON($data);
        }
    }

    /**
     * H5商城、百度小程序首页分类
     */
    public function h5shop()
    {
        $list=app('mysqlbxd_mall_common')->select("select * from category_group order by cg_sort  ");
        foreach ($list as &$row){
            $row['cg_icon']=FileHelper::getFileUrl($row['cg_icon']);
        }
        $this->responseJSON($list);
    }
    /**
     * H5商城、百度小程序首页分类详情
     */
    public function h5shopByCgid()
    {
        $cgid = app()->request()->params('cg_id');
        $cgid=intval($cgid);
        $cg=$list=null;

        if($cgid){
            $cg=app('mysqlbxd_mall_common')->fetch("select cg_name,c_ids from category_group where cg_id='{$cgid}'");
            if($cg && $cg['c_ids'] && $c_ids=array_filter(explode(',',$cg['c_ids']))){
                $tmplist=app('mysqlbxd_mall_common')->select("select c_id,c_name from category where c_id in(".implode(',',$c_ids).")  ");
                if($tmplist){
                    $tmplist=array_column($tmplist,null,'c_id');
                    $list[]=[
                        'c_id'=>implode(',',array_keys($tmplist)),
                        'c_name'=>'全部',
                    ];
                    foreach ($c_ids as $cid){
                        if(isset($tmplist[$cid])){
                            $list[]=$tmplist[$cid];
                        }
                    }
                }
            }
        }
        $data=[
            'categoryGroup'=>$cg?$cg:(object)[],
            'categoryList'=>$list?$list:[],
        ];
        $this->responseJSON($data);
    }

    /**
     * 获取商品分类
     */
    public function getList()
    {
        $level = app()->request()->params('level', 1);
        //获取所有的分类
        $goodsCategory = new GoodsCategory();
        if ($level == 2) {
            $data = $goodsCategory->getCategories(['parentIds' => 0]);
        } else {
            $data = $goodsCategory->getListByParentId(['parentIds' => 0]);
        }

        $this->responseJSON($data);
    }

    /**
     * 获取商品分类的子类及子类下的商品
     * @throws ParamsInvalidException
     */
    public function getByParentId()
    {
        $data = [];
        $categoryId = app()->request()->params('id');
        if (empty($categoryId)) {
            throw new ParamsInvalidException("参数不正确");
        }
        //获取分类下的二级分类
        $goodsCategory = new GoodsCategory();
        $categoryData = $goodsCategory->getListByParentId(['parentIds' => $categoryId]);
        foreach ($categoryData as $categoryItem) {
            //获取分类下的商品
            $goodsList = [];
            list($goodsData) = ItemManager::listItem(0, 0, 3, ['categoryId' => $categoryItem['c_id']]);
            if ($goodsData) {
                foreach ($goodsData as $goodsItem) {
                    $surfaceImg = json_decode(stripslashes($goodsItem['g_surfaceImg']), true);
                    $goodsList[] = [
                        'g_id' => $goodsItem['g_id'],
                        'g_surfaceImg' => $surfaceImg['gi_img'] = FileHelper::getFileUrl($surfaceImg['gi_img'], 'mall_goods_attr_images')
                    ];
                }
            }
            $data[] = [
                'c_id' => $categoryItem['c_id'],
                'c_name' => $categoryItem['c_name'],
                'goods_onsale_count' => $categoryItem['goods_onsale_count'],
                'goods_list' => $goodsList
            ];
        }

        $this->responseJSON($data);
    }
}
