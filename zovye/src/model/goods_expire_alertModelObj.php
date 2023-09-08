<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class goods_expire_alertModelObj extends modelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('goods_expire_alert');
    }
    
	/** @var int */
	protected $id;

	/** @var int */
	protected $agent_id;

	/** @var int */
	protected $device_id;

	/** @var int */
	protected $lane_id;

	/** @var int */
	protected $goods_id;

	/** @var int */
	protected $expired_at;

	/** @var text */
	protected $extra;

	/** @var int */
	protected $createtime;

	use \zovye\traits\ExtraDataGettersAndSetters;
}