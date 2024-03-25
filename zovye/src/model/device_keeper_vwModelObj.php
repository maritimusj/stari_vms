<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\domain\Keeper;
use function zovye\tb;

/**
 * Class device_keeper_vwModelObj
 * @package zovye
 * @method getKeeperId()
 * @method getKind()
 * @method getWay()
 * @method getCommissionFixed()
 * @method getCommissionFreeFixed()
 * @method getCommissionPercent()
 * @method getCommissionFreePercent()
 */
class device_keeper_vwModelObj extends deviceModelObj
{
    /** @var int */
    protected $keeper_id;

    protected $kind;
    protected $way;

    /** @var int */
    protected $commission_percent;

    /** @var int */
    protected $commission_fixed;

    /** @var int */
    protected $commission_free_percent;

    /** @var int */
    protected $commission_free_fixed;

    /** @var int */
    protected $device_qoe_bonus_percent; //设备电费分成比例 0 ~ 100

    /** @var int */
    protected $app_online_bonus_percent; //app在线分成比例 0 ~ 100

    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($read_or_write == self::OP_READ) {
            return tb('device_keeper_vw');
        }

        trigger_error('user getTableName(...) miss op!');

        return '';
    }

    public function isFixedValue(): bool
    {
        return $this->commission_fixed != -1;
    }

    function getKeeper(): ?keeperModelObj
    {
        return Keeper::get($this->keeper_id);
    }
}


