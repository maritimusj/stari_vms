<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;
/**
 * @method getDeviceId()
 * @method getCate()
 * @method getUserId()
 */
class device_recordModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
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