<?php
/**
 * 订单
 * @author Administrator
 *
 */
namespace Controller\Mall\Order;

use Lib\Base\BaseController;
use Exception\ServiceException;
use Exception\ParamsInvalidException;
use Exception\ModelException;

class Sales extends BaseController
{

    private $goodsLib = null;

    private $orderLib = null;

    public function __construct()
    {
        parent::__construct();
        $this->goodsLib = new \Lib\Mall\Goods();
        $this->orderLib = new \Lib\Mall\Order();
    }
    /**
     * 物流订单号查询
     */
    public function logistics()
    {
        $data = [];
        
        $params = app()->request()->params();
        
        if (! isset($params['osn'])) {
            throw new ParamsInvalidException("订单号必须");
        }
        
        if (!isset($params['companyName']))
        {
            throw new ParamsInvalidException("快递公司必须");
        }
        
        $expressLib = new \Lib\Mall\Express();
        
        $companyList = $expressLib->companyList([]);
        
        if ( !isset($companyList[ $params['companyName'] ]) ) {
            throw new ParamsInvalidException("快递公司不存在");
        }
        $companyCard = $params['companyName'];
        
        $kdNiaoClass = new \kdNiao\kdNiao();
        
        $orderTraces = $kdNiaoClass->orderTracesSubByJson($params['osn'],$companyCard);
        
        $orderTraces = json_decode($orderTraces,true);
       //print_r($orderTraces);exit;
        if ($orderTraces['Success'] && !isset($orderTraces['Reason']))
        {
            $data['state'] = $orderTraces['State'];
            $data['list'] = $orderTraces['Traces'];
        }
        else{
            throw new ParamsInvalidException($orderTraces['Reason']);
        }
        //throw new ParamsInvalidException("订单号不存在");
        $this->responseJSON($data);
    }


    /**
     * 验证快递单号是否正确
     * @return array
     */
    public function checkExpressData()
    {
        $params = app()->request()->params();
        $data = ['isCorrect' => false, 'maybeShipper' => []];
        $expressSn = isset($params['expressSn']) ? $params['expressSn'] : '';
        $expressCompanyCode = isset($params['expressCompany']) ? $params['expressCompany'] : '';
        if ($expressSn && $expressCompanyCode) {
            $orderTraces = (new \kdNiao\kdNiao())->getExpressData($expressSn);
            $orderTraces = json_decode($orderTraces, true);
            if ($orderTraces['Success'] && !empty($orderTraces['Shippers'])) {
                foreach ($orderTraces['Shippers'] as $shipper) {
                    if ($shipper['ShipperCode'] == $expressCompanyCode) {
                        $data['isCorrect'] = true;
                        break;
                    }
                }

                if (!$data['isCorrect']) {
                    $data['maybeShipper'] = [
                        'shipperCode' => $orderTraces['Shippers'][0]['ShipperCode'],
                        'shipperName' => $orderTraces['Shippers'][0]['ShipperName']
                    ];
                }
            }
        }

        $this->responseJSON($data);
    }

    /**
     * 更新订单物流信息
     */
    public function update()
    {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        if (!isset($params['id'])) {
            throw new ParamsInvalidException("订单Id必须");
        }
        //获取订单信息
        $resQuery = $this->orderLib->query(array(
            'salesUid' => $this->uid,
            'id' => $params['id']
        ));
        if (empty($resQuery['list'])) {
            throw new ServiceException("订单不存在");
        }
        $orderData = $resQuery['list'][0];

        $resMall = $this->orderLib->update($params);
        if ($resMall && $orderData['o_isSelfPickup'] == 1 && $orderData['o_status'] == 1) {
            //自提订单发货时，订单状态自动变为"已收货"
            $params = [];
            $params['uid'] = $orderData['u_id'];
            $params['osn'] = $orderData['o_sn'];
            $resMall = $this->orderLib->finish($params);
        }
        $this->responseJSON($resMall);
    }

    /**
     * 订单确认收货
     */
    public function finish()
    {
        $params = app()->request()->params();
        $params['uid'] = $this->uid;
        if (! isset($params['osn'])) {
            throw new ParamsInvalidException("订单号必须");
        }
        $resQuery = $this->orderLib->query(
            array(
                'uid' => $this->uid,
                'osn' => $params['osn']
            ));
        if ($resQuery['count'] < 1) {
            throw new ServiceException("订单不存在");
        }
        $resMall = $this->orderLib->finish($params);
        $this->responseJSON($resMall);
    }
}
