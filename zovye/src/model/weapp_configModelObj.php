<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * Class weapp_configModelObj
 * @package zovye
 * @method getName()
 * @method getCreatetime()
 * @method getLocked_uid()
 * @method setLocked_uid(string $UNLOCKED)
 * @method getLockedUid()
 * @method setLockedUid(string $UNLOCKED)
 */
class weapp_configModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;

    /** @var string */
    protected $name;

    protected $data;
    /** @var int */
    protected $createtime;

    protected $locked_uid;

    public static function getTableName($readOrWrite): string
    {
        return tb('weapp_config');
    }
}