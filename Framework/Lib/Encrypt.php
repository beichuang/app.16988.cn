<?php

namespace Framework\Lib;

/**
 *
 */
class Encrypt
{
    /**
    * 采用RC4为核心算法，通过加密或者解密用户信息
    *
    * @param string $string - 加密或解密的串
    * @param string $operation - DECODE 解密；ENCODE 加密
    * @param string $key - 密钥 默认为$SETTING['member']['authkey']
    * @param string $expiry - 过期时间
    * @param string $agent - 是否在加密过程中使用用户代理参加key的计算，1：使用，0：不使用，其他：直接使用传递过来的值
    * @return string 返回字符串
    */
    public static function rc4($string, $operation = 'DECODE', $key = '', $expiry = 0, $agent = '')
    {
        /**
        * $ckeyLength 随机密钥长度 取值 0-32;
        * 加入随机密钥，可以令密文无任何规律，即便是原文和密钥完全相同，加密结果也会每次不同，增大破解难度。
        * 取值越大，密文变动规律越大，密文变化 = 16 的 $ckeyLength 次方
        * 当此值为 0 时，则不产生随机密钥
        */
        $ckeyLength = 4;
        $key = md5($key);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckeyLength ? ($operation == 'DECODE' ? substr($string, 0, $ckeyLength): substr(md5(microtime()), -$ckeyLength)) : '';

        $cryptkey = $keya.md5($keya.$keyc);
        $keyLength = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckeyLength))
            : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $stringLength = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $keyLength]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $stringLength; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0)
                && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)
            ) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }
    }

    /**
     * 加密
     *
     */
    public static function encodeRC4($str, $key = '', $expiry = 0, $agent = '')
    {
        return self::rc4($str, 'ENCODE' , $key, $expiry, $agent);
    }
    /**
     * 解密
     *
     */
    public static function decodeRC4($str, $key = '', $expiry = 0, $agent = '')
    {
        return self::rc4($str, 'DECODE' , $key, $expiry, $agent);
    }
}
