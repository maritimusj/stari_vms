<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use function zovye\tb;

/**
 * Class device_viewModelObj
 * @package zovye
 */
class device_viewModelObj extends deviceModelObj
{
    /** @var int int */
    protected $m_total = 0;
    /** @var int int */
    protected $d_total = 0;

    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($readOrWrite == self::OP_READ) {
            return tb('device_view');
        }

        trigger_error('user getTableName(...) miss op!');

        return '';
    }


    /**
     * 获取设备本月统计
     * @param array $way
     * @param string $month
     * @return int
     */
    public function getMTotal($way = [], $month = 'this month'): int
    {
        if (count($way) == 1 && in_array('total', $way) && $day = 'this month') {
            return $this->m_total;
        }

        return parent::getMTotal($way, $month);
    }

    /**
     * 获取设备的今日统计
     * @param array $way
     * @param string $day
     * @return int
     */
    public function getDTotal($way = [], $day = 'today'): int
    {
        if (count($way) == 1 && in_array('total', $way) && $day = 'today') {
            return $this->d_total;
        }

        return parent::getDTotal($way, $day);
    }
}
