<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class packageModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('package');
    }
    
    /** @var int */
	protected $id;

    /** @var int */
	protected $device_id;

    /** @var string */
	protected $title;

    /** @var int */
	protected $price;

    /** @var int */
	protected $createtime;

}