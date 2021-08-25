<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class cacheModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('cache');
    }

    /** @var int */
    protected $id;

    /** @var string */
    protected $uid;

    /** @var string */
    protected $data;

    /** @var int */
    protected $createtime;

    /** @var int */
    protected $expiretime;

    /** @var int */
    protected $updatetime;

    public function isExpired(): bool
    {
        return $this->expiretime && $this->expiretime > time();
    }
}