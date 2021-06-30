<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\User;
use zovye\Locker;
use zovye\Inventory;
use function zovye\tb;
use zovye\InventoryLog;
use zovye\base\modelObj;
use zovye\InventoryGoods;
use zovye\traits\ExtraDataGettersAndSetters;

class inventoryModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
		return tb('inventory');
    }
    
	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $parent_id;

	/** @var string */
	protected $uid;

	/** @var string */
	protected $title;

	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;

	public function format(): array
	{
		$data = [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'createtime' => $this->getCreatetime(),
			'createtime_formatted' => date('Y-m-d H:i:s', $this->getCreatetime()),
		];

		$parent_id = $this->getParentId();
		if ($parent_id) {
			$parent = Inventory::get($parent_id);
			if ($parent) {
				$data['parent'] = [
					'id' => $parent->getId(),
					'title' => $this->getTitle(),
				];
			}
		}

		$owner = $this->getExtraData('user', []);
		if ($owner) {
			$user = User::get($owner['id']);
			if ($user) {
				$data['user'] = $user->profile();
			} else {
				$data['user'] = $owner;
			}
		}
		return $data;
	}

	public function query($cond = [])
	{
		$cond['inventory_id'] = $this->id;
		return InventoryGoods::query($cond);
	}

	public function logQuery()
	{
		return InventoryLog::query(['inventory_id' => $this->id]);
	}

    /**
     * 锁定
     * @param string $name
     * @return lockerModelObj|null
     */
    public function acquireLocker(): ?lockerModelObj
    {
        return Locker::try("inventory:{$this->getId()}:default", 6);
    }

	public function stock($src_inventory, $goods, $num, $extra = null): ?inventory_logModelObj
	{
		if ($src_inventory instanceof inventoryModelObj) {
			$src_inventory_id = $src_inventory->getId();
		} elseif (is_int($src_inventory)) {
			$src_inventory_id = $src_inventory;
		} elseif (empty($src_inventory)) {
			$src_inventory_id = 0;
		} else {
			return null;
		}

		if ($goods instanceof goodsModelObj) {
			$goods_id = $goods->getId();
		} elseif (is_int($goods)) {
			$goods_id = $goods;
		} elseif (is_array($goods) && !empty($goods['id'])) {
			$goods_id = intval($goods['id']);
		} else {
			return null;
		}

		if ($num == 0) {
			return null;
		}

		$inventory_goods = InventoryGoods::findOne([
			'inventory_id' => $this->id,
			'goods_id' => $goods_id,			
		]);
		if ($inventory_goods) {
			$inventory_goods->setNum($inventory_goods->getNum() + $num);
			if (!$inventory_goods->save()) {
				return null;
			}
		} else {
			if (!InventoryGoods::create([
				'inventory_id' => $this->id,
				'goods_id' => $goods_id,
				'num' => $num,
			])) {
				return null;
			}
		}

		$log_data = [
			'src_inventory_id' => $src_inventory_id,
			'inventory_id' => $this->id,
			'goods_id' => $goods_id,
			'num' => intval($num),
		];

		if (isset($extra)) {
			$log_data['extra'] = $extra;
		}

		return InventoryLog::create($log_data);
	}

}