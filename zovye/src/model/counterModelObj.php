<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

/**
 * @method getNum()
 */
class counterModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('counter');
    }

    /** @var int */
    protected $id;

    /** @var string */
    protected $uid;

    /** @var int */
    protected $num;

    /** @var int */
    protected $createtime;

    /** @var int */
    protected $updatetime;

}