<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\model\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\m;
use function zovye\tb;

/**
 * Class goods_voucherModelObj
 * @package zovye
 * @method getId();
 * @method getUserId();
 * @method getGoodsId();
 * @method setGoodsId($goods_id);
 * @method getNum();
 * @method getUsedTime();
 * @method getExpired();
 * @method getCreatetime();
 * @method getEnable();
 * @method getAgentId();
 * @method getTotal();
 * @method getBegin();
 * @method getEnd();
 * @method setBegin($begin);
 * @method setEnd($end);
 * @method setTotal($total);
 * @method setEnable($enable);
 * @method getCode()
 * @method getOwnerId()
 * @method getUsedUserId()
 * @method getVoucherId()
 * @method getDeviceId()
 */
class goods_voucherModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $enable;
    protected $agent_id;
    protected $goods_id;
    protected $total;
    protected $extra;
    protected $begin;
    protected $end;
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == self::OP_WRITE) {
            return tb('goods_voucher');
        } elseif ($read_or_write == self::OP_READ) {
            return tb('goods_voucher_vw');
        }

        trigger_error('user getTableName(...) miss op!');

        return '';
    }

    public function getUsedTotal(): int
    {
        return m('goods_voucher_logs')->where(['voucher_id' => $this->id])->count();
    }

    use ExtraDataGettersAndSetters;
}