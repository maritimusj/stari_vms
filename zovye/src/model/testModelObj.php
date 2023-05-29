<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class testModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('test');
    }

	/** @var smallint */
	protected $id;

	/** @var bigint */
	protected $uniacid;

	/** @var float */
	protected $user_id;

	/** @var double */
	protected $name;

	/** @var timestamp */
	protected $name2;

	/** @var char */
	protected $name3;

	/** @var blob */
	protected $name5;

	/** @var tinytext */
	protected $name6;

	/** @var time */
	protected $nam7;


}