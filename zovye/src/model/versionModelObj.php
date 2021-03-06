<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * Class versionModelObj
 * @package zovye
 * @method getUrl()
 * @method getTitle()
 * @method getVersion()
 * @method getCreatetime()
 */
class versionModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $title;
    protected $version;
    protected $url;
    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('version');
    }
}