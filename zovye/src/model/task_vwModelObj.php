<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class task_vwModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('task_vw');
    }
	/** @var int */
	protected $id;

	/** @var int */
	protected $user_id;

	/** @var int */
	protected $account_id;

	/** @var int */
	protected $s1;

	/** @var varchar */
	protected $s2;

	/** @var text */
	protected $extra;

	/** @var int */
	protected $createtime;

	/** @var smallint */
	protected $state;


	use \zovye\traits\ExtraDataGettersAndSetters;
}