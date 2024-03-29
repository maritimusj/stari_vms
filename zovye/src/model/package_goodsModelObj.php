<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Goods;
use function zovye\tb;

/**
 * @method getPrice()
 * @method getNum()
 * @method getGoodsId()
 */
class package_goodsModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('package_goods');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $package_id;

    /** @var int */
    protected $goods_id;

    /** @var int */
    protected $price;

    /** @var int */
    protected $num;

    /** @var int */
    protected $createtime;

    public function getGoods(): ?goodsModelObj
    {
        return Goods::get($this->getGoodsId(), true);
    }
}