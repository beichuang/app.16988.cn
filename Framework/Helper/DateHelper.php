<?php
namespace Framework\Helper;


class DateHelper
{
    public static function getTimeSpanFormat($beginTime, $endTime)
    {
        $formatText = '';
        if (is_string($beginTime)) {
            $beginTime = strtotime($beginTime);
        }
        if (is_string($endTime)) {
            $endTime = strtotime($endTime);
        }

        $res = timediff($beginTime, $endTime);
        if ($res['day'] > 0) {
            $formatText .= $res['day'] . '天';
        }
        if ($res['hour'] > 0) {
            $formatText .= $res['hour'] . '小时';
        }
        if ($res['day'] <= 0 && $res['min'] > 0) {
            $formatText .= $res['min'] . '分';
        }
        if ($res['day'] <= 0 && $res['hour'] <= 0 && $res['sec'] > 0) {
            $formatText .= $res['sec'] . '秒';
        }

        return $formatText;
    }
}