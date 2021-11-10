<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use function zovye\m;

use function zovye\tb;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

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

    public static function getTableName($readOrWrite): string
    {
        if ($readOrWrite == self::OP_WRITE) {
            return tb('goods_voucher');
        } elseif ($readOrWrite == self::OP_READ) {
            return tb('goods_voucher_view');
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