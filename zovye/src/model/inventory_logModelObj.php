<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\Goods;
use function zovye\tb;
use zovye\base\modelObj;
use zovye\Inventory;
use zovye\traits\ExtraDataGettersAndSetters;

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

	public function getGoods()
	{
		return Goods::get($this->goods_id);
	}

	public function getSrcInventory()
	{
		return Inventory::get($this->src_inventory_id);
	}
	
}