<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use function zovye\tb;
use function zovye\m;

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
            return tb('device_keeper_view');
        }
        trigger_error('user getTableName(...) miss op!');
        return '';
    }

    function getKeeper(): ?keeperModelObj
    {
        return m('keeper')->findOne(['id' => $this->keeper_id]);
    }
}


