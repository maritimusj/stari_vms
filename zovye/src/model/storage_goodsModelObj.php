<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class storage_goodsModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
		if ($readOrWrite == self::OP_READ) {
            return tb('storage_goods_vw');
        }

		return tb('storage_goods');
    }
    
	/** @var int */
	protected $id;

	/** @var int */
	protected $storage_id;

	/** @var int */
	protected $goods_id;

	/** @var int */
	protected $num;

	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;
}