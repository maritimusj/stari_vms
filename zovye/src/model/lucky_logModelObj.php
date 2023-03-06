<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class lucky_logModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('lucky_log');
    }
    
	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $lucky_id;

	/** @var int */
	protected $user_id;

	/** @var string */
	protected $serial;

	/** @var string */
	protected $name;

	/** @var string */
	protected $phone_num;

	/** @var string */
	protected $location;

	/** @var string */
	protected $address;

	/** @var int */
	protected $status;

	protected $extra;

	/** @var int */
	protected $createtime;


	use \zovye\traits\ExtraDataGettersAndSetters;
}