<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\Agent;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\User;
use function zovye\tb;

class vipModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('vip');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $agent_id;

	/** @var int */
	protected $user_id;

	protected $extra;

	/** @var int */
	protected $createtime;

    use ExtraDataGettersAndSetters;

    public function getUser(): ?userModelObj {
        return User::get($this->user_id);
    }

    public function getAgent(): ? agentModelObj {
        return Agent::get($this->agent_id);
    }


}