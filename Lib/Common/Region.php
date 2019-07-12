<?php
namespace Lib\Common;

/**
 * 地域信息公共类
 *
 * @author Administrator
 *        
 */
class Region
{

    private static $commonCacheableApiClient = null;

    private static $regionData = null;
    
    private static $provinces = null;

    private static $citys = null;
    
    private static $areas = null;
    
    private static $fileCacher = null;

    const COUNTRY='全国';
    
    /**
     * 获取common-api客户端
     */
    private static function getClient()
    {
        if (self::$commonCacheableApiClient == null) {
            self::$commonCacheableApiClient = get_api_client('CommonData', 'CacheableApiClient');
        }
        return self::$commonCacheableApiClient;
    }
    
    /**
     * 获取文件缓存类
     */
    private static function  getFileCacher()
    {
        if(self::$fileCacher==null){
            $cacheDir=config('app.fileCache.dir');
            $cacheLifetime=config('app.fileCache.lifetime');
            self::$fileCacher=new \Cache\File\FileCache($cacheDir,$cacheLifetime);
        }
        return self::$fileCacher;
    }
    
    /**
     * 获取文件缓存名称的前缀
     */
    private static function getCacheIdPrefix()
    {
        $prefix=str_replace(["/","\\",":"," "], "_", __FILE__);
    }
    
    /**
     * 初始化省市区数据
     */
    private static function init()
    {
        if(empty(self::$provinces) || empty(self::$citys) ||empty(self::$areas)){
            $fileCacher=self::getFileCacher();
            $cacheId=self::getCacheIdPrefix();
            $data=$fileCacher->get($cacheId);
            if(!$data || ! is_array($data)){
                $regionData=self::getAllRegionData();
                foreach ($regionData as $row){
                    if($row['region_level']==1){
                        $data['provinces'][$row['region_code']]=$row['region_name'];
                    }else if ($row['region_level']==2){
                        $data['citys'][$row['region_pcode']][$row['region_code']]=$row['region_name'];
                    }else if ($row['region_level']==3){
                        $data['areas'][$row['region_pcode']][$row['region_code']]=$row['region_name'];
                    }
                }
                $fileCacher->save($cacheId,$data);
            }
            self::$provinces=$data['provinces'];
            self::$citys=$data['citys'];
            self::$areas=$data['areas'];
        }
    }
    
    /**
     * 得到省 如果省传null则返回 全部省数组， 如果省对应的key不存在则返回COUNTRY常量
     * @param int $provinceCode
     * @return string
     */
    public static function getProvinces($provinceCode = null)
    {
        self::init();
        if (is_null($provinceCode)) {
            return self::$provinces;
        } else {
            return isset(self::$provinces[$provinceCode]) ? self::$provinces[$provinceCode] : self::COUNTRY;
        }
    }
    /**
     * 查询某个省下面所有城市
     * @param int $provinceCode
     * @return boolean
     */
    public static function getCities($provinceCode)
    {
        self::init();
        return isset(self::$citys[$provinceCode]) ? self::$citys[$provinceCode] : false;
    }

    /**
     * 查询某个市下面所有地区
     * @param int $cityCode
     * @return boolean
     */
    public static function getAreas($cityCode)
    {
        self::init();
        return isset(self::$areas[$cityCode]) ? self::$areas[$cityCode] : false;
    }
    
    /**
     * 获取所有地区信息数据
     */
    public static function getAllRegionData()
    {
        if (self::$regionData == null) {
            self::$regionData = self::get(array());
        }
        return self::$regionData;
    }

    /**
     * 根据地区编号查询地区信息
     *
     * @param int $regionCode            
     * @return array boolean
     */
    public static function getRegionInfoByCode($regionCode)
    {
        $data = self::getAllRegionData();
        try {
            return $data[$regionCode];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 根据地区编号查询地区名称
     *
     * @param int $regionCode            
     * @return string
     */
    public static function getRegionNameByCode($regionCode)
    {
        $regionInfo = self::getRegionInfoByCode($regionCode);
        if ($regionInfo && is_array($regionInfo) && isset($regionInfo['region_name'])) {
            return $regionInfo['region_name'];
        }
        return "";
    }

    /**
     * 根据Ip查询省市区编码
     *
     * @param string $ip            
     * @throws \Exception\InternalException
     * @return array
     */
    public static function getRegionInfoByIp($ip)
    {
        $params = array(
            'ip' => $ip
        );
        $qRes = self::getClient()->doRequest("ip/get", $params);
        if ($qRes['error_code']) {
            throw new \Exception\InternalException($qRes['error_msg']);
        }
        $data = $qRes['data'];
        $province_code = $data['re_province_code'] ? $data['re_province_code'] : 0;
        $city_code = $data['re_city_code'] ? $data['re_city_code'] : 0;
        $area_code = $data['re_area_code'] ? $data['re_area_code'] : 0;
        $full_name = $data['re_full_name'] ? $data['re_full_name'] : '';
        return array(
            $province_code,
            $city_code,
            $area_code,
            $full_name
        );
    }

    /**
     * 查询地域信息
     *
     * @param array $params            
     * @return unknown multitype:
     */
    public static function get($params)
    {
        $data = self::getClient()->getData('region/get', $params);
        if ($data && is_array($data) && isset($data['list'])) {
            return $data['list'];
        }
        return [];
    }

    /**
     * 按省市名称查地域信息
     *
     * @param string $province
     *            省名称
     * @param string $city
     *            市名称
     * @return array [$provinceCode,$cityCode]
     */
    public static function getRegionCodeByName($province, $city)
    {
        $res=[0,0];
        if($province){
            $region_data = self::getAllRegionData();
            $region= self::queryRegionInfoByName($province, $city, $region_data);
            if($region){
                if($region['region_level']==1){
                    $res=[$region['region_code'],0];
                }else if($region['region_level']==2){
                    $res=[$region['region_pcode'],$region['region_code']];
                }
            }
        }
        return $res;
    }

    /**
     * 按省市名称查地域信息
     *
     * @param string $province
     *            省名称
     * @param string $city
     *            市名称
     * @param array $region_data            
     * @return Ambigous <NULL, unknown>
     */
    public static function queryRegionInfoByName($province, $city, $region_data)
    {
        $region_info = null;
        foreach ($region_data as $region) {
            if ($province && $city) {
                $city_pre = mb_substr($city, 0, 2);
                if (mb_strpos($region['region_name'], $city_pre) === 0 && intval($region['region_level']) === 2 &&
                     isset($region['region_fullname']) &&
                     (mb_strpos($region['region_fullname'], mb_substr($province, 0, 2)) === 0)) {
                    $region_info = $region;
                    break;
                }
            } else if ($province) {
                if ($region['region_name'] == $province && intval($region['region_level']) === 1) {
                    $region_info = $region;
                    break;
                }
            }
        }
        return $region_info;
    }

    public static function getRegionCode($provinceName,$cityName,$areaName)
    {
        $region_info = null;
        $regionCodes=self::getRegionCodeByName($provinceName,$cityName);
        $regionCodes[2]=0;
        if($regionCodes[0] && $regionCodes[1]){
            $areas=self::getAreas($regionCodes[1]);
            $max=0;
            foreach ($areas as $tmpAreaCode=>$tmpAreaName) {
                $sc=similar_text($areaName,$tmpAreaName);
                if($sc>$max){
                    $max=$sc;
                    $regionCodes[2]=$tmpAreaCode;
                }
            }
            if($max<6){
                $regionCodes[2]=0;
            }
        }else{
            $regionCodes[2]=0;
        }
        return $regionCodes;
    }
}
