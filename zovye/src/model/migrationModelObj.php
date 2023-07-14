<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

/**
 * @method string getName()
 */
class migrationModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('migration');
    }

    /** @var int */
    protected $id;
    /** @var int */
    protected $uniacid;
    /** @var string */
    protected $name
    ;
    protected $filename;
    /** @var  int */
    protected $result;
    /** @var string */
    protected $error;
    /** @var int */
    protected $begin;
    /** @var int */
    protected $end;
    /** @var int */
    protected $createtime;
}