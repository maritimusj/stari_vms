<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * @method getTitle()
 * @method getCount()
 * @method getCreatetime()
 * @method setCount(int $param)
 */
class tagsModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $title;
    protected $count;
    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('tags');
    }
}