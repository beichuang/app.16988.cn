<?php
namespace Controller\Common;

use Exception\ServiceException;
use Lib\Base\BaseController;

class Region extends BaseController
{

    /**
     * 查询地区信息
     */
    public function get()
    {
        $params = $this->app->request->params();
        $withKeys = $this->app->request->params('with_keys', 1);
        $showTree = $this->app->request->params('showTree');
        $allowParams=['with_keys','showTree','region_code','region_name','is_query_sub'];
        $currentReqTimesKeys = "common-region-get-req-times";
        $currentReqTimes=app('redis')->get($currentReqTimesKeys);
        $currentReqTimes=$currentReqTimes?intval($currentReqTimes):0;
        app('redis')->setex($currentReqTimesKeys,1,++$currentReqTimes);
        if($currentReqTimes>10){
            throw new ServiceException('请稍候再试!');
        }
        foreach ($params as $pk=>$pv){
            if(!in_array($pk,$allowParams) || $pv===''){
                unset($params[$pk]);
            }
        }
        if(isset($params['with_keys'])){
            $withKeys=$withKeys?1:0;
            unset($params['with_keys']);
        }
        if(isset($params['showTree'])){
            $showTree=$showTree?($showTree==2?2:1):0;
            unset($params['showTree']);
        }
        if(!$params) {
            $cacheKey = "region-cache-{$showTree}-{$withKeys}";
            $data = app('redis')->get($cacheKey);
            if($data){
                $output = '
            {"error_type": 0,
              "error_code": 0,
              "error_msg": "",
              "data": '.$data.'
            }';
                app()->response()->header('Content-Type','application/json');
                die($output);
            }
        }
        $rows = \Lib\Common\Region::get($params);

        if ($showTree) {
            $data = array();
            for ($i=1; $i <= 3; $i++) {
                foreach ($rows as $k => $value) {
                    if ($value['region_level'] != $i) {
                        continue;
                    }
                    if ($showTree == 1) {
                        $key = $value['region_code'];
                        $full = 'region_fullcode';
                        $splitString = ":";
                    } else {
                        $key = $value['region_name'];
                        $full = 'region_fullname';
                        $splitString = "/";
                    }
                    switch ($value['region_level']) {
                        case '1':
                            $data[$key] = array('region_code'=>$value['region_code'], 'region_name'=>$value['region_name']);
                            break;
                        case '2':
                            list($level1, $level2) = explode($splitString, $value[$full]);
                            if ( isset($data[$level1]) ) {
                                $data[$level1]['son'][$key] = array('region_code'=>$value['region_code'], 'region_name'=>$value['region_name']);
                            }
                            break;
                        case '3':
                            list($level1, $level2, $level3) = explode($splitString, $value[$full]);
                            if ( isset($data[$level1]['son'][$level2]) ) {
                                $data[$level1]['son'][$level2]['son'][$key] = array('region_code'=>$value['region_code'], 'region_name'=>$value['region_name']);
                            }
                            break;
                    }
                }
            }
        } else {
            $data = &$rows;
        }

        if (! $withKeys && $data) {
            $data = array_values($data);
        }
        if(!$params) {
            $cacheKey = "region-cache-{$showTree}-{$withKeys}";
            app('redis')->setex($cacheKey,800000,json_encode($data));
        }
        $this->responseJSON($data);
    }

    public function getList()
    {
        /** @var \Redis $redis */
        $redis = app('redis');
        $cacheKey = 'region-list';
        $data = $redis->get($cacheKey);
        if (empty($data)) {
            $rows = \Lib\Common\Region::get([]);
            if ($rows) {
                $firstLevelData = [];
                $secondLevelData = [];
                $thirdLevelData = [];
                foreach ($rows as $row) {
                    if ($row['region_level'] == 1) {
                        $firstLevelData[$row['region_code']] = $row;
                    } elseif ($row['region_level'] == 2) {
                        $secondLevelData[$row['region_code']] = $row;
                    } elseif ($row['region_level'] == 3) {
                        $thirdLevelData[$row['region_code']] = $row;
                    }
                }

                foreach ($thirdLevelData as $thirdLevelItem) {
                    if (isset($secondLevelData[$thirdLevelItem['region_pcode']])) {
                        $secondLevelData[$thirdLevelItem['region_pcode']]['son'][] = [
                            'region_code' => $thirdLevelItem['region_code'],
                            'region_name' => $thirdLevelItem['region_name']
                        ];
                    }
                }

                foreach ($secondLevelData as $key => $secondLevelItem) {
                    if ($key != 'son' && isset($firstLevelData[$secondLevelItem['region_pcode']])) {
                        $firstLevelData[$secondLevelItem['region_pcode']]['son'][] = [
                            'region_code' => $secondLevelItem['region_code'],
                            'region_name' => $secondLevelItem['region_name'],
                            'son' => isset($secondLevelItem['son']) ? $secondLevelItem['son'] : []
                        ];
                    }
                }

                foreach ($firstLevelData as $firstLevelItem) {
                    $data[] = [
                        'region_code' => $firstLevelItem['region_code'],
                        'region_name' => $firstLevelItem['region_name'],
                        'son' => $firstLevelItem['son']
                    ];
                }
            }

            $redis->set($cacheKey, json_encode($data));
        } else {
            $data = json_decode($data, true);
        }

        $this->responseJSON($data);
    }

    /**
     * 查询省份code
     */
    public function getProvinces()
    {
        $province = $this->app->request->params('province', '');
        
        if (!$province)
        {
            throw new \Exception\ParamsInvalidException("省份输入有误");
        }
        $province = str_replace("省","",$province);
        
        $rows = \Lib\Common\Region::getProvinces();
        
        $province = array_search($province,$rows);

        if (!$province)
        {
            throw new \Exception\ParamsInvalidException("没有找到省份编码");
        }
        
        $data['provinceCode'] = $province;
        
        $this->responseJSON($data);
    }
    
    /**
     * 查询省和市code
     */
    public function getProvinceCityCode()
    {
        $province = $this->app->request->params('province', '');
    
        $city = $this->app->request->params('city', '');
        $area = $this->app->request->params('area', '');
        if (!$province || !$city)
        {
            throw new \Exception\ParamsInvalidException("省市输入有误");
        }
        
        $province = str_replace("省","",$province);
        $city = str_replace("市","",$city);
        
        $rows = \Lib\Common\Region::getProvinces();
        $province = array_search($province,$rows);
    
        if (!$province)
        {
            throw new \Exception\ParamsInvalidException("没有找到省份编码");
        }
        
        $city_rows = \Lib\Common\Region::getCities($province);
        
        $city = array_search($city,$city_rows);
        
        if (!$city)
        {
            throw new \Exception\ParamsInvalidException("没有找到城市编码");
        }

        if ($city && $area)
        {
            $area_rows = \Lib\Common\Region::getAreas($city);
            $areacode = array_search($area, $area_rows);
            if (!$areacode)
            {
                throw new \Exception\ParamsInvalidException("没有找到地区编码");
            }
            $data['areaCode'] = $areacode;
        }
    
        $data['provinceCode'] = $province;
    
        $data['cityCode'] = $city;
        
        $this->responseJSON($data);
    }
    
    public function currentIp()
    {
        $ip = get_client_ip();
        $ip = '218.28.136.59';
        list ($provinceCode, $cityCode, $areaCode, $regionFullName) = \Lib\Common\Region::getRegionInfoByIp($ip);
        $this->responseJSON(
            [
                'provinceCode' => $provinceCode,
                'cityCode' => $cityCode,
                'areaCode' => $areaCode,
                'regionFullName' => $regionFullName,
                'clientIP' => $ip
            ]);
    }
}
