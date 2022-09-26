<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class teamModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('team');
    }
    
    /** @var int */
	protected $uniacid;

    /** @var int */
	protected $owner_id;

    /** @var string */
	protected $name;

    /** @var int */
	protected $createtime;

}