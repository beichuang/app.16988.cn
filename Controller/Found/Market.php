<?php
/**
 * 发现 
 * @author Administrator
 *
 */
namespace Controller\Found;

use Lib\Base\BaseController;
use Exception\ModelException;
use Exception\ServiceException;

class Market extends BaseController
{

    protected $MarketModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->MarketModel = new \Model\Found\Market();
    }

    /**
     * 新增附近市场
     *
     * @throws ModelException
     */
    public function lists()
    {
        $provinceCode = app()->request()->params('provinceCode');
        $cityCode = app()->request()->params('cityCode');
        $areaCode = app()->request()->params('areaCode');
        $page = app()->request()->params('page');
        $pageSize = app()->request()->params('pageSize');
        if (! isset($page)) {
            $page = 1;
        }
        if (! isset($pageSize)) {
            $pageSize = 10;
        }
        if (isset($provinceCode) && $provinceCode) {
            $this->handleData($provinceCode, 'int');
        }
        if (! $cityCode) {
            throw new \Exception\ParamsInvalidException("市编号必须");
        }
        $this->handleData($cityCode, 'int');
        if (isset($areaCode) && $areaCode) {
            $this->handleData($areaCode, 'int');
        }
        $params = array(
            'm_provinceCode' => isset($provinceCode) ? $provinceCode : "",
            'm_cityCode' => $cityCode,
            'm_areaCode' => isset($areaCode) ? $areaCode : ""
        );
        $regRes = $this->MarketModel->lists($params, $page, $pageSize);
        
        $this->responseJSON($regRes[0]);
    }

    /**
     * 对数据进行初级过滤
     *
     * @param string $data
     *            要处理的数据
     * @param string $filter
     *            过滤的方式
     * @return mix
     */
    private function handleData($data = '', $filter = '')
    {
        switch ($filter) {
            case 'int':
                return abs(intval($data));
                break;
            
            case 'str':
                return trim(htmlspecialchars(strip_tags($data)));
                break;
            
            case 'float':
                return floatval($data);
                break;
            
            case 'arr':
                return (array) $data;
                break;
        }
        
        return '';
    }
}