<?php

namespace Framework\Lib;

/**
 * 验证类
 *
 *
 */
class Validation
{
    /**
    * 验证???件地???
    *
    * @param string $str
    * @return boolean
    */
    function checkEmail($str)
    {
        return preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str);##
    }

    /**
    * 验证URL地址
    *
    * @param string $str
    * @return boolean
    */
    public static function checkUrl($str)
    {
        return preg_match("|^http://[_=&///?\.a-zA-Z0-9-]+$|i", $str);##
    }

    /**
    * 全英文字???
    *
    * @param string $str
    * @param integer $len
    * @return boolean
    */
    public static function checkAlpha($str, $len = 0)
    {
        if (is_int($len) && ($len > 0))
        {
            return preg_match("/^([a-z]{".$len."})$/i", $str);
        }
        else
        {
            return preg_match("/^([a-z])+$/i", $str);
        }
    }

    /**
    * 全数???
    *
    * @param string $str
    * @param integer $len
    * @return boolean
    */
    public static function checkNumber($str, $len = 0)
    {
        if (is_int($len) && ($len > 0))
        {
            return preg_match("/^([0-9]{".$len."})$/", $str);
        }
        else
        {
            return preg_match("/^([0-9])+$/", $str);
        }
    }

    /**
    * 数字或字???
    *
    * @param string $str
    * @param integer $len
    * @return boolean
    */
    public static function checkNumAlpha($str, $len = 0)
    {
        if (is_int($len) && ($len > 0))
        {
            return preg_match("/^([a-z0-9]{".$len."})$/i", $str);
        }
        else
        {
            return preg_match("/^([a-z0-9])+$/i", $str);
        }
    }

    /**
    * 数字和字母的组合
    *
    * @param string $str
    * @param integer $len
    * @return boolean
    */
    public static function checkBlend($str, $len = 0 ,$maxLen = 0)
    {
        if (is_int($maxLen) && ($maxLen > 0))
        {
            if (!$this->checkLen($str, $len, $maxLen))
            {
                return FALSE;
                exit;
            }

        }
        elseif (is_int($len) && ($len > 0) && !$maxLen)
        {
            if (strlen($str) > $len)
            {
                return FALSE;
                exit;
            }
        }
        return preg_match("/^(((\d+[a-z]+)|([a-z]+\d+))[0-9a-z]*)$/i", $str);
    }

    /**
    * 数字和字母或上划???,下划???
    *
    * @param string $str
    * @param integer $len
    * @return boolean
    */
    public static function checkDash($str, $len = 0)
    {
        if (is_int($len) && ($len > 0))
        {
            return preg_match("/^([_a-z0-9-]{".$len."})$/i", $str);
        }
        else
        {
            return preg_match("/^([_a-z0-9-])+$/i", $str);
        }
    }

    /**
    * ???点数
    *
    * @param string $str
    * @return boolean
    */
    public static function checkFloat($str)
    {
        return preg_match("/^[0-9]+\.[0-9]+$/", $str);
    }

    /**
    * ???大长???
    *
    * @param string $str
    * @param integer $length
    * @return boolean
    */
    public static function checkMax($str, $length)
    {
        return (@strlen($str) <= $length);
    }

    /**
    * ???小长???
    *
    * @param string $str
    * @param integer $length
    * @return boolean
    */
    public static function checkMin($str, $length)
    {
        return (@strlen($str) >= $length);
    }

    /**
    * ???否一???
    *
    * @param string $strA
    * @param strint $strB
    * @return boolean
    */
    public static function checkSame($strA, $strB)
    {
        return ($strA == $strB) ? TRUE : FALSE;

    }

    /**
    * 指定长度
    *
    * @param string $str
    * @param integer $minLen
    * @param integer $maxLen
    * @return boolean
    */
    public static function checkLen($str, $minLen, $maxLen)
    {
        $strLen = @strlen($str);
        if (($strLen >= $minLen) && ($strLen <= $maxLen))
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    /**
    * ??????
    *
    * @param string $str
    * @param integer $len
    * @return boolean
    */
    public static function checkChinese($str, $len = 0)
    {
        if (is_int($len) && ($len > 0))
        {
            $len = $len * 2;
            return preg_match("/^[".chr(0xa1)."-".chr(0xff)."]{".$len."}$/", $str);
        }
        else
        {
            return preg_match("/^[".chr(0xa1)."-".chr(0xff)."]+$/", $str);
        }
    }

    /**
    * IP地址验证
    *
    * @param string $str
    * @return boolean
    */
    public static function checkIp($str)
    {
        $exp = array();
        if ($exp = explode('.', $str))
        {
            foreach ($exp as $val)
            {
                if ($val > 255)
                {
                    return FALSE;
                    exit;
                }
            }
        }
        return preg_match("/^[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}$/", $str);
    }

    /**
    * 日期格式验证
    *
    * @param string $str
    * @return boolean
    */
    public static function checkIsDate($str)
    {
        $exp = array();
        if ($exp = explode('/', $str))
        {
            if (count($exp) == 3)
            {
                $str = implode('-', $exp);
            }
        }
        if ($exp = explode('-', $str))
        {
            if (count($exp) != 3 || $exp[1] > 12 || $exp[2] > 31)
            {
                return FALSE;
                exit;
            }
        }
        return preg_match("/^([1-9][\d])?[\d][\d][-|\/][\d]{1,2}[-|\/][\d]{1,2}$/", $str);
    }

    /**
    *  时间格式验证
    *
    * @param string $str
    * @return boolean
    */
    public static function checkIsTime($str)
    {
        $exp = array();
        if ($exp = explode(':', $str))
        {
            if (count($exp) != 3 || $exp[0] > 23 || $exp[1] > 59 || $exp[2] > 59)
            {
                return FALSE;
                exit;
            }
        }
        return preg_match("/^[\d]{1,2}:[\d]{1,2}:[\d]{1,2}$/", $str);
    }

    /**
    * 电话号码
    *
    * @param string $str
    * @return boolean
    */
    public static function checkPhone($str)
    {
        return preg_match("/^(\d{3,4}-)?(\d{7,8})$/", $str);
    }

    /**
    * 手机号码
    *
    * @param string $str
    * @return boolean
    */
    public static function checkMobile($str)
    {
        return preg_match("/^1\d{10}$/", $str);
    }

    /**
    * ???政编???
    *
    * @param string $str
    * @return boolean
    */
    public static function checkZip($str)
    {
        return preg_match("/^[1-9]\d{5}$/", $str);
    }

    /**
    * ???定义正则验证
    *
    * @param string $str
    * @param string $type
    * type为???则表达示格式，??? /[a-z]+[\d]{3,5}/i
    * @return boolean
    */
    public static function checkCustom($str, $type)
    {
        return preg_match($type, $str);
    }

    /**
    * 多项验证
    *
    * @param array $strArr
    * @return array
    */
    public static function checkSundry($strArr)
    {
        $returnArr = $classMethods = $funcArr = array();
        if (is_array($strArr))
        {
            $classMethods = get_class_methods('Validation');
            foreach ($classMethods as $methods)
            {
                $funcArr[] = strtoupper($methods);
            }
            foreach ($strArr as $key=>$val)
            {
                if (is_array($val))
                {
                    $func = "check".$val[0];
                    if (!in_array(strtoupper($func), $funcArr))
                    {
                        echo 'ERROR: The '.$func.' method has not defined!';
                        exit;
                    }
                    if ($val[3])
                    {
                        $returnArr[] = $this->$func($val[1], $val[2] ,$val[3]);
                    }
                    elseif ($val[2] && !$val[3])
                    {
                        $returnArr[] = $this->$func($val[1], $val[2]);
                    }
                    else
                    {
                        $returnArr[] = $this->$func($val[1]);
                    }
                }
            }
        }
        return $returnArr;
    }

}
?>
