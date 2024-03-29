<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

/**
 * Class filesModelObj
 * @package zovye
 * @method getTitle()
 * @method setTitle($title)
 * @method getType()
 * @method setType($type)
 * @method getUrl()
 * @method setUrl($url)
 * @method getTotal()
 * @method setTotal($total)
 * @method getCreatetime()
 */
class filesModelObj extends ModelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $title;
    protected $type;
    protected $url;
    protected $total;
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('files');
    }
}