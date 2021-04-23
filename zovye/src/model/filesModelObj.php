<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

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
class filesModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $title;
    protected $type;
    protected $url;
    protected $total;
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('files');
    }
}