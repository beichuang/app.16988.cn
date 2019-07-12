<?php

namespace Framework\Helper;

class Arr
{

    /*
     * 将数组扁平化
     * array('a' => array('b' => 'c')) 将转化为 array('a.b' => 'c')
     * @param $arr 原始数组
     * @param $peparator 分割符 默认为 '.'
     * @return 
     */
    public static function flatten($arr, $separator = '.', $prepend = '')
    {
        $result = array();
        foreach ($arr as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $result = array_merge($result, static::flatten($value, $separator, $prepend.$key.$separator));
            } else {
                $result[$prepend.$key] = $value;
            }
        }
        return $result;
    }
}
