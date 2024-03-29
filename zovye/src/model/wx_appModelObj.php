<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

/**
 * @method getName()
 * @method getKey()
 * @method getSecret()
 * @method getCreatetime()
 */
class wx_appModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('wx_app');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $uniacid;

    /** @var string */
    protected $name;

    /** @var string */
    protected $key;

    /** @var string */
    protected $secret;

    /** @var int */
    protected $createtime;

}