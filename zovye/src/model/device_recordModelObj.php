<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

/**
 * @method getDeviceId()
 * @method getCate()
 * @method getUserId()
 */
class device_recordModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('device_record');
    }

    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $user_id;

    protected $cate;

    /** @var int */
    protected $agent_id;

    /** @var int */
    protected $createtime;

}