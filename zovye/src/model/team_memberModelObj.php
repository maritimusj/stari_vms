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

/**
 * @method getMobile();
 * @method setMobile(string $mobile);
 * @method setName(string $name);
 * @method getName();
 * @method getRemark();
 * @method setRemark(string $remark);
 */
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

    public function profile($detail = true): array
    {
        $user = $this->user();

        $data = [
            'id' => $this->getId(),
            'mobile' => $this->mobile,
            'name' => $this->name,
            'remark' => $this->remark,
            'createtime_formatted' => date('Y-m-d H:i:s', $this->createtime),
        ];

        if ($detail) {
            $user = $this->user();
            $data['user'] = $user ? $user->profile() : [];
        }
        return $data;
    }
}