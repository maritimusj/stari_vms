<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

class commissionModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('commission');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $category;

	/** @var int */
	protected $agent_id;

	/** @var int */
	protected $user_id;

	/** @var int */
	protected $rel;

	/** @var int */
	protected $formula;

	/** @var int */
	protected $type;

	/** @var int */
	protected $val;

	/** @var int */
	protected $createtime;
}