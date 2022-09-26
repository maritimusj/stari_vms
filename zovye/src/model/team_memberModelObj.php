<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use zovye\Team;
use zovye\User;
use function zovye\tb;

class team_memberModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('team_member');
    }

    /** @var int */
    protected $team_id;

    /** @var int */
    protected $user_id;

    /** @var string */
    protected $mobile;

    /** @var string */
    protected $name;

    /** @var string */
    protected $remark;

    /** @var int */
    protected $createtime;

    public function team(): ?teamModelObj
    {
        return Team::get($this->team_id);
    }

    public function user(): ?userModelObj
    {
        if ($this->user_id > 0) {
            return User::get($this->user_id);
        }

        return null;
    }

    public function profile(): array
    {
        $user = $this->user();

        return [
            'id' => $this->getId(),
            'user' => $user ? $user->profile() : [],
            'mobile' => $this->mobile,
            'name' => $this->name,
            'remark' => $this->remark,
            'createtime_formatted' => date('Y-m-d H:i:s', $this->createtime),
        ];
    }
}