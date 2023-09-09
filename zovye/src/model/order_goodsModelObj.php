<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class order_goodsModelObj extends modelObj
{
    public static function getTableName($read_or_write): string
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