<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\User;
use function zovye\tb;

/**
 * @method getOwnerId();
 * @method setOwnerId()
 */
class teamModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('team');
    }

    /** @var int */
    protected $uniacid;

    /** @var int */
    protected $owner_id;

    /** @var string */
    protected $name;

    /** @var int */
    protected $createtime;

    public function owner(): ?userModelObj
    {
        return User::get($this->owner_id);
    }

    public function profile(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'createtime_formatted' => date('Y-m-d H:i:s', $this->createtime),
        ];
    }
}