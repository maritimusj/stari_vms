<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

use function zovye\tb;

/**
 * @method getCount()
 * @method setCount($count)
 * @method getOpenid()
 * @method setOpenid($openid)
 * @method getAdvsId()
 * @method setAdvsId($id)
 * @method getDeviceId()
 * @method setDeviceId($id)
 * @method getAccountId()
 * @method setAccountId($id)
 * @method getIp()
 * @method setIp($ip)
 * @method getCreatetime()
 */
class advs_statsModelObj extends modelObj
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $uniacid;

    /** @var int */
    protected $count;

    /** @var string */
    protected $openid;

    /** @var int */
    protected $advs_id;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $account_id;

    /** @var int */
    protected $ip;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($read_or_write): string
    {
        return tb('advs_stats');
    }

}