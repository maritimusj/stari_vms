<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Agent;
use zovye\domain\User;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method getName();
 * @method getMobile();
 * @method getAgentId();
 * @method getCreatetime();
 * @method getUserId()
 */
class vipModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
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

    /** @var string */
    protected $name;

	/** @var string */
	protected $mobile;

	protected $extra;

	/** @var int */
	protected $createtime;

    use ExtraDataGettersAndSetters;

    public function getUser(): ?userModelObj {
        if ($this->user_id) {
            return User::get($this->user_id, false, User::WxAPP);
        }
        if ($this->mobile) {
            return User::findOne(['mobile' => $this->mobile, 'app' => User::WxAPP]);
        }
        return null;
    }

    public function getAgent(): ? agentModelObj {
        return Agent::get($this->agent_id);
    }

    public function getDeviceIds(): array
    {
        return (array)$this->getExtraData('device.ids', []);
    }

    public function setDeviceIds($ids)
    {
        return $this->setExtraData('device.ids', (array)$ids);
    }

    public function getDevicesTotal(): int
    {
        return count($this->getDeviceIds());
    }

    public function hasPrivilege(deviceModelObj $device): bool
    {
        return $device->getAgentId() == $this->getAgentId() && in_array($device->getId(), $this->getDeviceIds());
    }
}