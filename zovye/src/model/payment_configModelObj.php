<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\ModelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class payment_configModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('payment_config');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $agent_id;

	/** @var int */
	protected $name;

	/** @var string */
	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;
}