<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use function zovye\tb;

/**
 * @method getData()
 * @method setData(false|string $json_encode)
 * @method setUpdatetime(int $time)
 */
class cacheModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
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