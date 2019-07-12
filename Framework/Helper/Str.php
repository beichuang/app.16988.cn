<?php

namespace Framework\Helper;

class Str
{
    /**
     * Return the length of the given string.
     *
     * @param  string  $value
     * @return int
     */
    public static function length($value)
    {
        return mb_strlen($value);
    }

    /**
     * Limit the number of characters in a string.
     *
     * @param  string  $value
     * @param  int     $limit
     * @param  string  $end
     * @return string
     */
    public static function limit($value, $limit = 100, $end = '...')
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')).$end;
    }

    /**
     * Generate "random" alpha-numeric string.
     *
     * @param  int  $length
     * @param  int  $t upper-number 类似linux文件权限，每位用二进制表示 11表示有大写字符、有数字。
     *        10表示有大写字符没有数字
     * @return string
     */
    public static function random($length = 16, $t = 3)
    {
        $string = '';
        $randomParamMap = array(
            array(10, 48),
            array(26, 65),
            array(26, 97),
        );

        $hasNum = $t & 1;
        $hasUpper = ($t & 2) >> 1 ;

        $i = 0;
        while($i < $length)
        {
            $randomParam = mt_rand(0, 2);
            if (($randomParam === 0 && !$hasNum) || ($randomParam === 1 && !$hasUpper)) {
                $randomParam = 2;
            }
            $string .= chr((mt_rand(1, 100000) % $randomParamMap[$randomParam][0]) + $randomParamMap[$randomParam][1]);
            $i++;
        }

        return $string;
    }

    /**
     * Returns the portion of string specified by the start and length parameters.
     *
     * @param  string  $string
     * @param  int  $start
     * @param  int|null  $length
     * @return string
     */
    public static function substr($string, $start, $length = null)
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }
}
