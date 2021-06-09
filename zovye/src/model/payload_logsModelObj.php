<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class payload_logsModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('payload_logs');
    }

    /** @var int */
	protected $id;

     /** @var int */
	protected $uniacid;

     /** @var int */
	protected $device_id;

     /** @var int */
	protected $goods_id;

     /** @var int */
	protected $org;

     /** @var int */
	protected $num;

     /** @var int */
	protected $change;

	protected $extra;

     /** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;
}