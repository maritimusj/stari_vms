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
 * Class balanceModelObj
 * @package zovye
 * @method getOpenid()
 * @method getXVal()
 * @method getSrc()
 * @method getExtra()
 * @method getCreatetime()
 */
class balanceModelObj extends modelObj
{
    /** @var int */
    protected $id;
    /** @var int */
    protected $uniacid;
    /** @var string */
    protected $openid;
    /** @var int */
    protected $x_val;
    /** @var int */
    protected $src;

    protected $extra;
    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('balance');
    }
}