<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * @method setTotal($param)
 * @method getUrl()
 * @method getTotal()
 * @method getTitle()
 * @method setTitle($title)
 * @method getContent()
 * @method setContent($content)
 * @method getCreatetime()
 */
class articleModelObj extends modelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    protected $type;

    protected $title;

    protected $content;

    /** @var int */
    protected $total;

    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('article');
    }
}