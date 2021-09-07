<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class counterModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
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