<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use function zovye\tb;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class order_goodsModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('order_goods');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $order_id;

    /** @var int */
    protected $goods_id;

    protected $result;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;
}