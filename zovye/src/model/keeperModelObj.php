<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\base\ModelObjFinder;
use zovye\domain\Agent;
use zovye\domain\CommissionValue;
use zovye\domain\Keeper;
use zovye\domain\User;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\m;
use function zovye\tb;

/**
 * @method getName()
 * @method getMobile()
 * @method getAgentId()
 * @method getCreatetime()
 * @method setName($name)
 * @method setMobile($mobile)
 */
class keeperModelObj extends ModelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    /** @var string */
    protected $name;
    /** @var string */
    protected $mobile;
    /** @var int */
    protected $agent_id;
    protected $extra;
    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($read_or_write): string
    {
        return tb('keepers');
    }

    public function getUser(): ?userModelObj
    {
        return User::findOne(['mobile' => $this->mobile, 'app' => User::WX]);
    }

    /**
     * @param deviceModelObj $device
     * @return CommissionValue|null
     */
    public function getCommissionValue(deviceModelObj $device): ?CommissionValue
    {
        $device_id = $device->getId();
        /** @var keeper_devicesModelObj $res */
        $res = m('keeper_devices')->findOne(
            [
                'device_id' => $device_id,
                'keeper_id' => $this->getId(),
            ]
        );

        if ($res) {
            return $res->getCommissionValue();
        }

        return null;
    }

    public function getAppOnlineBonusPercent(deviceModelObj $device): int
    {
        $device_id = $device->getId();
        /** @var keeper_devicesModelObj $res */
        $res = m('keeper_devices')->findOne(
            [
                'device_id' => $device_id,
                'keeper_id' => $this->getId(),
            ]
        );
        return $res ? $res->getAppOnlineBonusPercent() : 0;
    }

    public function getDeviceQoeBonusPercent(deviceModelObj $device): int
    {
        $device_id = $device->getId();
        /** @var keeper_devicesModelObj $res */
        $res = m('keeper_devices')->findOne(
            [
                'device_id' => $device_id,
                'keeper_id' => $this->getId(),
            ]
        );
        return $res ? $res->getDeviceQoeBonusPercent() : 0;
    }

    public function deviceQuery(): ModelObjFinder
    {
        return m('keeper_devices')->where(['keeper_id' => $this->getId()]);
    }

    /**
     * @param deviceModelObj $device
     * @return int
     */
    public function getKind(deviceModelObj $device): int
    {
        $device_id = $device->getId();
        /** @var keeper_devicesModelObj $res */
        $res = m('keeper_devices')->findOne(
            [
                'device_id' => $device_id,
                'keeper_id' => $this->getId(),
            ]
        );

        if ($res) {
            return intval($res->getKind());
        }

        return 0;
    }

    public function getAgent(): ?agentModelObj
    {
        return Agent::get($this->agent_id);
    }

    public function setCommissionLimitTotal($total = null)
    {
        return $this->setExtraData('commission.limit_total', $total);
    }

    public function getCommissionLimitTotal(): int
    {
        return $this->getExtraData('commission.limit_total', -1);
    }
}
