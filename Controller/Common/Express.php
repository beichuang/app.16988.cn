<?php
namespace Controller\Common;

use Controller\User\Common;
use Lib\Base\BaseController;

class Express extends BaseController
{

    private $expressLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->expressLib = new \Lib\Mall\Express();
    }

    /**
     * 快递公司列表
     */
    public function companyList()
    {
        $list = $this->expressLib->companyList(array());
        foreach ($list as $k => $v) {
            $ret[] = ['key' => $k, 'name' => $v];
        }

        $this->responseJSON($ret);
    }
    /**
     * public function code(){
     * $code1   = [];
     * for($i=1;$i<=20;$i++){
     * $common  = new Common();
     * $code    =  $common->generateCode();
     * $code1[] = $code;
     * }
     * dd($code1);
     * }
     **/
    /**
     * 热门搜索词接口
     */
    public function searchWords()
    {
        //后台配置的收录
        $s = 'select svalue from setting where skey=:skey';
        $configWords = app('mysqlbxd_mall_common')->select($s, ['skey' => 'goods_search_hot_words']);
        if ($configWords) {
            $configWords = array_column($configWords, 'svalue');
            $configWords = explode(',', $configWords[0]);
        } else {
            $configWords = [];
        }
        //热门搜索
        $sql = "SELECT keywords FROM hot_search_words  ORDER BY num  DESC,update_time  DESC  LIMIT 0,3";
        $hotWords = app('mysqlbxd_app')->select($sql);
        if ($hotWords) {
            $hotWords = array_column($hotWords, 'keywords');
        } else {
            $hotWords = [];
        }
        $searchWords = array_merge($configWords, $hotWords);
        $this->responseJSON($searchWords);
    }

    //根据对应分类  出现关键词
    public function searchKeywords()
    {
        $category = app()->request()->params('category');
        if (!trim($category)) {
            throw new \Exception\ParamsInvalidException("分类名称有误");
        }
        //热门搜索
        $sql = "SELECT keywords FROM  category_keywords  where category=:category";
        $where = ["category" => $category];
        $hotWords = app('mysqlbxd_app')->fetchColumn($sql, $where);
        if ($hotWords) {
            $hotWords = explode(',', $hotWords);
        } else {
            $hotWords = [];
        }
        $this->responseJSON($hotWords);
    }

    /**
     * 根据商品种类  定义尺寸
     * 1:平尺  2:库存件数  3:长*宽  4：长*宽*高 5：自定义
     * @throws \Exception\ParamsInvalidException
     */
    public function searchCategorySize()
    {
        $key = app()->request()->params('key', '');
        if (!trim($key)) {
            throw new \Exception\ParamsInvalidException("分类名称有误");
        }
        $sizeType = isset(config('GCSize')[$key]) ? config('GCSize')[$key] : 5;
        $this->responseJSON($sizeType);
    }







}