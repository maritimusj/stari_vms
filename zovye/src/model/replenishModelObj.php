<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use function zovye\tb;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method getDeviceUid()
 * @method getAgentId()
 * @method getKeeperId()
 * @method getGoodsId()
 * @method getOrg()
 * @method getNum()
 * @method getCreatetime()
 * @method getExtra()
 */
class replenishModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $device_uid;
    /** @var int */
    protected $agent_id;
    /** @var int */
    protected $keeper_id;
    /** @var int */
    protected $goods_id;
    protected $org;
    /** @var int */
    protected $num;
    protected $extra;
    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('replenish');
    }
}