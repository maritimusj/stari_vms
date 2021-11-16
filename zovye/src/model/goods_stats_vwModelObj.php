<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * Class goods_stats_vwModelObj
 * @package zovye
 * @method getAgentId()
 * @method getDeviceId()
 * @method getName()
 * @method getTotal()
 * @method getDate()
 */
class goods_stats_vwModelObj extends modelObj
{
    /** @var int */
    protected $id;
    /** @var int */
    protected $agent_id;
    /** @var int */
    protected $device_id;
    protected $name;
    /** @var int */
    protected $total;
    protected $date;

    public static function getTableName($readOrWrite): string
    {
        return tb('goods_stats_vw');
    }
}