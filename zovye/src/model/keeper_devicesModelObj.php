<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Keeper;
use function zovye\tb;

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
 * @method getCommissionFreeFixed()
 * @method getCommissionFreePercent()
 */
class keeper_devicesModelObj extends ModelObj
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

    /** @var int */
    protected $commission_free_percent;

    /** @var int */
    protected $commission_free_fixed;

    protected $way; //佣金类型 0 销售分成 1 补货分成

    protected $kind; //补货权限 0 没有 1 有

    /** @var int */
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('keeper_devices');
    }

    public function isFixedValue(): bool
    {
        return $this->commission_fixed != -1;
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

    public function setCommissionFreePercent($percent, $way = Keeper::COMMISSION_ORDER)
    {
        $this->commission_free_percent = $percent;
        $this->commission_free_fixed = -1;
        $this->way = $way;
        $this->setDirty(['commission_free_percent', 'way', 'commission_free_fixed']);
    }

    public function setCommissionFreeFixed($fixed, $way = Keeper::COMMISSION_ORDER)
    {
        $this->commission_free_fixed = $fixed;
        $this->commission_free_percent = -1;
        $this->way = $way;
        $this->setDirty(['commission_free_percent', 'way', 'commission_free_fixed']);
    }

    /**
     *
     * @return array
     */
    public function getCommissionValue(): array
    {
        if ($this->isFixedValue()) {
            return [$this->commission_fixed, intval($this->way), false];
        }

        return [$this->commission_percent / 100, intval($this->way), true];
    }

        /**
     *
     * @return array
     */
    public function getFreeCommissionValue(): array
    {
        if ($this->commission_free_percent != -1) {
            return [$this->commission_free_percent / 100, intval($this->way), true];
        }

        if ($this->commission_free_fixed != -1) {
            return [$this->commission_free_fixed, intval($this->way), false];
        }

        return Keeper::DEFAULT_COMMISSION_VAL;
    }
}
