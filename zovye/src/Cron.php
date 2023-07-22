<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class Cron
{
    public static function query($condition = []): base\modelObjFinder
    {
        return m('cron')->query(We7::uniacid([]))->where($condition);
    }

    public static function create($uid, $url, $spec, $extra = null)
    {
        $data = We7::uniacid([
            'uid' => $uid,
            'url' => $url,
            'spec' => $spec,
        ]);

        if ($extra) {
            $data['extra'] = json_encode($extra);
        }

        return m('cron')->create($data);
    }

    public static function getList($uid)
    {
        return self::query(['uid' => $uid])->findAll();
    }

    public static function describe($cronExpression): string
    {
        // 解析cron表达式
        $cronParts = explode(' ', $cronExpression);

        // 检查cron表达式是否有效
        if (count($cronParts) != 6) {
            return '无效的cron表达式';
        }

        // 分别描述cron表达式的各个部分
        $second = self::describeCronPart($cronParts[0], '秒', '秒钟');
        $minute = self::describeCronPart($cronParts[1], '分', '分钟');
        $hour = self::describeCronPart($cronParts[2], '点', '小时');
        $dayOfMonth = self::describeCronPart($cronParts[3], '天', '天');
        $month = self::describeCronPart($cronParts[4], '月', '月份');
        $dayOfWeek = self::describeCronPart($cronParts[5], '星期', '星期');

        // 返回描述结果
        return "秒钟：$second\n分钟：$minute\n小时：$hour\n日期：$dayOfMonth\n月份：$month\n星期：$dayOfWeek";
    }

    public static function describeCronPart($part, $u1, $u2): string
    {
        // 检查cron部分是否为通配符
        if ($part == '*') {
            return "每$u2";
        }

        // 检查cron部分是否为范围
        if (strpos($part, '-') !== false) {
            $rangeParts = explode('-', $part);

            return "从$rangeParts[0]到$rangeParts[1]$u2";
        }

        // 检查cron部分是否为递增步长
        if (strpos($part, '/') !== false) {
            $stepParts = explode('/', $part);

            return "每$stepParts[1]{$u1}，从$stepParts[0]开始";
        }

        // 检查cron部分是否为列表
        if (strpos($part, ',') !== false) {
            $listParts = explode(',', $part);
            $listDescription = implode('、', $listParts);

            return "$listDescription$u1";
        }

        // 单个值
        return "$part$u1";
    }
}