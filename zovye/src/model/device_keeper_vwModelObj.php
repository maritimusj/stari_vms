<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Keeper;

use function zovye\tb;

/**
 * Class device_keeper_vwModelObj
 * @package zovye
 * @method getKeeper_id()
 * @method getKind()
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

    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($readOrWrite == self::OP_READ) {
            return tb('device_keeper_vw');
        }
        trigger_error('user getTableName(...) miss op!');

        return '';
    }

    function getKeeper(): ?keeperModelObj
    {
        return Keeper::get($this->keeper_id);
    }
}


