<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Balance;
use function zovye\tb;

/**
 * Class users_vwModelObj
 * @method getFree_total()
 * @method getFee_total()
 * @method getBalanceTotal()
 * @method Balance getBalance()
 */
class users_vwModelObj extends userModelObj
{
    /** @var int */
    protected $free_total;
    /** @var int */
    protected $fee_total;
    /** @var int */
    protected $balance_total;
    /** @var int */
    protected $balance;

    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == self::OP_WRITE) {
            return parent::getTableName(self::OP_WRITE);
        } elseif ($readOrWrite == self::OP_READ) {
            return tb('users_vw');
        }
        trigger_error('user getTableName(...) miss op!');
        return '';
    }

    /**
     * 获取用户免费领取数量
     */
    public function getFreeTotal(): int
    {
        return intval($this->free_total);
    }

    /**
     * 获取用户支付领取数量
     */
    public function getFeeTotal(): int
    {
        return intval($this->fee_total);
    }
}
