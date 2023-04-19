<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Keeper;

use function zovye\tb;
use zovye\base\modelObj;

/**
 * @method getDeviceId()
 * @method getKeeperId()
 * @method getCommissionPercent()
 * @method getCommissionFixed()
 * @method setKind($kind)
 * @method getKind()
 * @method setWay($way)
 * @method getWay()
 * @method getCreatetime()
 * @method getName()
 * @method getImei()
 */
class keeper_devicesModelObj extends modelObj
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $keeper_id;

    /** @var int */
    protected $commission_percent;

    /** @var int */
    protected $commission_fixed;

    protected $way; //佣金类型 0 销售分成 1 补货分成

    protected $kind; //补货权限 0 没有 1 有

    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('keeper_devices');
    }

    public function setCommissionPercent($percent, $way = Keeper::COMMISSION_ORDER)
    {
        $this->commission_percent = $percent;
        $this->commission_fixed = -1;
        $this->way = $way;
        $this->setDirty(['commission_percent', 'way', 'commission_fixed']);
    }

    public function setCommissionFixed($fixed, $way = Keeper::COMMISSION_ORDER)
    {
        $this->commission_fixed = $fixed;
        $this->commission_percent = -1;
        $this->way = $way;
        $this->setDirty(['commission_percent', 'way', 'commission_fixed']);
    }

    /**
     *
     * @return array
     */
    public function getCommissionValue(): array
    {
        if ($this->commission_percent != -1) {
            return [$this->commission_percent / 100, intval($this->way), true];
        }

        if ($this->commission_fixed != -1) {
            return [$this->commission_fixed, intval($this->way), false];
        }

        return Keeper::DEFAULT_COMMISSION_VAL;
    }
}
