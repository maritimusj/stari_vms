<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObjFinder;
use zovye\User;
use zovye\Locker;
use zovye\Inventory;
use function zovye\tb;
use zovye\InventoryLog;
use zovye\base\modelObj;
use zovye\InventoryGoods;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method getTitle()
 * @method getParentId()
 * @method getUid()
 * @method setTitle(string $trim)
 */
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

	public function query($cond = []): modelObjFinder
    {
		$cond['inventory_id'] = $this->id;
		return InventoryGoods::query($cond);
	}

	public function getGoods($goods_id): ?inventory_goodsModelObj
    {
        return InventoryGoods::findOne(['inventory_id' => $this->id, 'goods_id' => $goods_id]);
    }

	public function logQuery(): modelObjFinder
    {
		return InventoryLog::query(['inventory_id' => $this->id]);
	}

    /**
     * 锁定
     * @return lockerModelObj|null
     */
    public function acquireLocker(): ?lockerModelObj
    {
        return Locker::try("inventory:{$this->getId()}:default", REQUEST_ID, 0, 6, 9999);
    }

	public function stock($src_inventory, $goods, int $num, array $extra = []): ?inventory_logModelObj
	{
		if ($num == 0) {
			return null;
		}

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

		$inventory_goods = InventoryGoods::findOne([
			'inventory_id' => $this->id,
			'goods_id' => $goods_id,			
		]);
		
		if ($inventory_goods) {
			$extra['before'] = $inventory_goods->getNum();
			$inventory_goods->setNum($inventory_goods->getNum() + $num);
			if (!$inventory_goods->save()) {
				return null;
			}
		} else {
			$extra['before'] = 0;
			$inventory_goods = InventoryGoods::create([
				'inventory_id' => $this->id,
				'goods_id' => $goods_id,
				'num' => $num,
			]);
			if (empty($inventory_goods)) {
				return null;
			}
		}

		$extra['after'] = $inventory_goods->getNum();

		$log_data = [
			'src_inventory_id' => $src_inventory_id,
			'inventory_id' => $this->id,
			'goods_id' => $goods_id,
			'num' => intval($num),
			'extra' => $extra,
		];

		return InventoryLog::create($log_data);
	}

}