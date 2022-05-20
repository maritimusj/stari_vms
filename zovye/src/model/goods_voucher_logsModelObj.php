<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * Class goods_voucher_logsModelObj
 * @package zovye
 * @method getGoodsId()
 * @method setUsedUserId($id)
 * @method setUsedTime($time)
 * @method getCode()
 */
class goods_voucher_logsModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $code;
    protected $owner_id;
    protected $voucher_id;
    protected $goods_id;
    protected $begin;
    protected $end;
    protected $used_time;
    protected $used_user_id;
    protected $device_id;
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('goods_voucher_logs');
    }

    public function isExpired(): bool
    {
        if (empty($this->end)) {
            return false;
        }

        return $this->end > 0 && $this->end < time();
    }

    public function isBegin(): bool
    {
        if (empty($this->begin)) {
            return true;
        }

        return $this->begin > 0 && $this->begin < time();
    }

    public function isUsed(): bool
    {
        return $this->used_user_id > 0;
    }

    public function isValid(): bool
    {
        return $this->isBegin() && !$this->isExpired() && !$this->isUsed();
    }
}