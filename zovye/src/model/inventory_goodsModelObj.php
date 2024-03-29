<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Goods;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method getNum()
 * @method setNum(int $param)
 */
class inventory_goodsModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        if ($read_or_write == self::OP_READ) {
            return tb('inventory_goods_vw');
        }

        return tb('inventory_goods');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $inventory_id;

    /** @var int */
    protected $goods_id;

    /** @var int */
    protected $num;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public function getGoods(): ?goodsModelObj
    {
        return Goods::get($this->goods_id);
    }
}