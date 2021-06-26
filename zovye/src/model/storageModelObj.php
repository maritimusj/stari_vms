<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

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

	/** @var int */
	protected $user_id;

	/** @var string */
	protected $uid;

	/** @var int */
	protected $title;

	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;
}