<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class balance_logsModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('balance_logs');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $user_id;

    /** @var int */
    protected $account_id;

    /** @var int */
    protected $s1;

    /** @var string */
    protected $s2;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;
}