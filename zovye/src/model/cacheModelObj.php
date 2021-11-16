<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
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
    protected $expiration;

    /** @var int */
    protected $updatetime;

    public function isExpired(): bool
    {
        return $this->expiration && $this->expiration < time();
    }
}