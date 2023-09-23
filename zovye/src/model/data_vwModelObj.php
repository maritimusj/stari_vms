<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

/**
 * @method getV()
 * @method setV(string $implode)
 * @method setCreatetime(float|int $_dt)
 * @method getK()
 */
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