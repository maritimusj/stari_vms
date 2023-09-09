<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\model\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method setName($getName)
 * @method setEnabled(true $true)
 */
class principalModelObj extends modelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('principal');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $user_id;

    /** @var int */
    protected $principal_id;

    /** @var bool */
    protected $enabled;

    /** @var string */
    protected $name;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;
}