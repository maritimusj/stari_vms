<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\Agent;
use zovye\base\modelObj;
use zovye\Util;

use function zovye\tb;

class luckyModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('lucky');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $agent_id;

	/** @var bool */
	protected $enabled;

	/** @var string */
	protected $name;

	/** @var string */
	protected $description;

	/** @var string */
	protected $image;

	protected $extra;

	/** @var int */
	protected $createtime;

	use \zovye\traits\ExtraDataGettersAndSetters;

	public function getRemark(): string
    {
        return $this->getExtraData('remark', '');
    }

	public function getAgent(): ?agentModelObj
	{
		if ($this->agent_id > 0) {
			return Agent::get($this->agent_id);
		}
		return null;
	}

	public function profile($fullpath = false): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'description' => $this->description,
            'remark' => $this->getRemark(),
			'image' => $fullpath ? Util::toMedia($this->image, $fullpath) : $this->image,
			'enabled' => $this->enabled,
			'createtime_formatted' => date('Y-m-d H:i:s', $this->createtime),
		];
	}
}