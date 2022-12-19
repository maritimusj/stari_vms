<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\User;
use zovye\Agent;
use zovye\Keeper;

use function zovye\m;
use function zovye\tb;
use zovye\base\modelObj;
use zovye\base\modelObjFinder;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method getName()
 * @method getMobile()
 * @method getAgentId()
 * @method getCreatetime()
 * @method setName($name)
 * @method setMobile($mobile)
 */
class keeperModelObj extends modelObj
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

    public static function getTableName($readOrWrite): string
    {
        return tb('keepers');
    }

    public function getUser(): ?userModelObj
    {
        return User::findOne(['mobile' => $this->mobile, 'app' => User::WX]);
    }

    /**
     * @param deviceModelObj $device
     * @return array
     */
    public function getCommissionValue(deviceModelObj $device): array
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

        return Keeper::DEFAULT_COMMISSION_VAL;
    }

    public function deviceQuery(): modelObjFinder
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

}
