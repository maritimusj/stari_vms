<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

/**
 * @method getName()
 */
class settings_userModelObj extends modelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('settings_user');
    }

    /** @var int */
    protected $id;
    protected $uniacid;
    protected $name;
    protected $data;
    /** @var int */
    protected $createtime;
}