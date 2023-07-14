<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Goods;
use function zovye\tb;
use zovye\base\modelObj;
use zovye\Inventory;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method getNum()
 */
class inventory_logModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('inventory_log');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $src_inventory_id;

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
        return Goods::get($this->goods_id, true);
    }

    public function getSrcInventory(): ?inventoryModelObj
    {
        return Inventory::get($this->src_inventory_id);
    }

}