<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\User;
use function zovye\tb;
use zovye\base\modelObj;
use zovye\Storage;
use zovye\traits\ExtraDataGettersAndSetters;

class storageModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
		return tb('storage');
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
			$parent = Storage::get($parent_id);
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
}