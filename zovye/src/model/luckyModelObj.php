<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
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
}