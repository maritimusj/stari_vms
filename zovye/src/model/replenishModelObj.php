<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Device;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

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
class replenishModelObj extends ModelObj
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

    public static function getTableName($read_or_write): string
    {
        return tb('replenish');
    }

    public function getDevice(): ?deviceModelObj
    {
        return Device::get($this->device_uid, true);
    }
}