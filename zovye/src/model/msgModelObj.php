<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\model\base\modelObj;
use function zovye\tb;

/**
 * @method getTitle()
 * @method getContent()
 * @method getCreatetime()
 * @method setContent($content)
 * @method setTitle(string $title)
 */
class msgModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $title;
    protected $content;
    /** @var int */
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('msg');
    }
}