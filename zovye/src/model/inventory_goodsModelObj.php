<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\Goods;
use function zovye\tb;
use zovye\base\modelObj;
use zovye\InventoryGoods;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method getNum()
 * @method setNum(int $param)
 */
class inventory_goodsModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
		if ($readOrWrite == self::OP_READ) {
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

	public function getGoods(): ?goodsModelObj {
		return Goods::get($this->goods_id);
	}
}