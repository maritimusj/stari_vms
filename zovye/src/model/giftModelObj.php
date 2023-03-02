<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use function zovye\tb;

use zovye\Agent;
use zovye\base\modelObj;
use zovye\Goods;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\Util;

/**
 * @method setAgentId(int $param)
 * @method setName(mixed $name)
 * @method setDescription(mixed $description)
 * @method setImage(mixed $image)
 */
class giftModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('gift');
    }
    
	public static function debugMode(): bool
	{
	    return true;
	}

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $agent_id;

	/** @var string */
	protected $name;

	/** @var string */
	protected $description;

	/** @var string */
	protected $image;

	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;

	public function getAgent(): ?agentModelObj
	{
		if ($this->agent_id > 0) {
			return Agent::get($this->agent_id);
		}
		return null;
	}

	public function getGoodsList($fullpath = false): array
	{
		$goods_list = $this->getExtraData('goods', []);

		$list = [];
		foreach($goods_list as $item) {
			$goods = Goods::get($item['id']);
			if ($goods) {
				$list[] = [
					'id' => $goods->getId(),
					'name' => $goods->getName(),
					'price' => $goods->getPrice() / 100,
					'image' => $fullpath ? Util::toMedia($goods->getImg(), $fullpath): $goods->getImg(),
					'unit_title' => $goods->getUnitTitle(),
					'gallery' => $goods->getGallery($fullpath),
					'num' => intval($item['num']),
				];
			}
		}
		return $list;
	}

	public function profile($fullpath = false): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
			'image' => $fullpath ? Util::toMedia($this->image, $fullpath) : $this->image,
			'createtime_formatted' => date('Y-m-d H:i:s', $this->createtime),
		];
	}

	public function format($fullpath = false): array
	{
		$data = $this->profile($fullpath);
		$data['list'] = $this->getGoodsList($fullpath);
		return $data;
	}

}