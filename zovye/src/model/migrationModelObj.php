<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class migrationModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('migration');
    }

    /** @var int */
	protected $id;
	/** @var int */
	protected $uniacid;
	/** @var string */
	protected $name
    /** @var string */;
	protected $filename;
	/** @var  int*/
	protected $result;
	/** @var string */
	protected $error;
	/** @var int */
	protected $begin;
	/** @var int */
	protected $end;
	/** @var int */
	protected $createtime;
}