<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * Class balanceModelObj
 * @package zovye
 * @method getOpenid()
 * @method getXVal()
 * @method getSrc()
 * @method getMemo()
 * @method getCreatetime()
 * @method setMemo(string $string)
 */
class balanceModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    /** @var string */
    protected $openid;
    /** @var int */
    protected $x_val;
    /** @var int */
    protected $src;
    /** @var string */
    protected $memo;
    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('balance');
    }

}