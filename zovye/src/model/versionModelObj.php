<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

/**
 * Class versionModelObj
 * @package zovye
 * @method getUrl()
 * @method getTitle()
 * @method getVersion()
 * @method getCreatetime()
 */
class versionModelObj extends ModelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $title;
    protected $version;
    protected $url;
    /** @var int */
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('version');
    }
}