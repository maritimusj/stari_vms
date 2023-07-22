<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class cronModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('cron');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var varchar */
	protected $uid;

	/** @var varchar */
	protected $spec;

	/** @var varchar */
	protected $url;

	/** @var int */
	protected $createtime;
}