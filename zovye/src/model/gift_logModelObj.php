<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use function zovye\tb;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class gift_logModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('gift_log');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $gift_id;

	/** @var int */
	protected $user_id;

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

	use ExtraDataGettersAndSetters;
}