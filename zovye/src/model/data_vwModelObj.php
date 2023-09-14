<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

class data_vwModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('data_vw');
    }

    /** @var int */
    protected $id;

    protected $k;

    protected $v;

    protected $createtime;

}