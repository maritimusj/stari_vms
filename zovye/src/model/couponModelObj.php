<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\model\base\modelObj;
use function zovye\tb;

/**
 * @method getUid()
 * @method getTitle()
 * @method setTitle($title)
 * @method getXVal()
 * @method setXVal($x)
 * @method getXRequire()
 * @method setXRequire($x)
 * @method getOwner()
 * @method setOwner($owner)
 * @method getUsedTime()
 * @method setUsedTime($time)
 * @method getExpiredTime()
 * @method setExpiredTime($x)
 * @method getMemo()
 * @method setMemo($x)
 * @method getCreatetime()
 */
class couponModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $uid;
    protected $title;
    protected $x_val;
    protected $x_require;
    protected $owner;
    protected $used_time;
    protected $expired_time;
    protected $memo;
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('coupon');
    }
}