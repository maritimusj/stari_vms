<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method getOrg()
 * @method getNum()
 * @method getGoodsId()
 * @method getLaneId()
 */
class payload_logsModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('payload_logs');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $uniacid;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $lane_id;

    /** @var int */
    protected $goods_id;

    /** @var int */
    protected $org;

    /** @var int */
    protected $num;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;
}