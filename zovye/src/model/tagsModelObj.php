<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use function zovye\tb;

/**
 * @method getTitle()
 * @method getCount()
 * @method getCreatetime()
 * @method setCount(int $param)
 */
class tagsModelObj extends \zovye\base\modelObj
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $uniacid;

    /** @var string */
    protected $title;

    /** @var int */
    protected $count;

    /** @var int */
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('tags');
    }
}