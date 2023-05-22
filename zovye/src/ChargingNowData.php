<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use zovye\model\charging_now_dataModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class ChargingNowData
{
    protected static function model(): base\modelFactory
    {
        return m('charging_now_data');
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return self::model()->where($condition);
    }

    public static function create($data)
    {
        return self::model()->create($data);
    }

    public static function get($serial): ?charging_now_dataModelObj
    {
        return self::query(['serial' => $serial])->findOne();
    }

    public static function set(
        $serial,
        userModelObj $user,
        deviceModelObj $device,
        int $charger_id
    ): ?charging_now_dataModelObj {
        return self::create([
            'serial' => $serial,
            'user_id' => $user->getId(),
            'device_id' => $device->getId(),
            'charger_id' => $charger_id,
            'createtime' => TIMESTAMP,
        ]);
    }

    public static function getByUser(userModelObj $user, string $serial): ?charging_now_dataModelObj
    {
        return self::model()->findOne([
            'serial' => $serial,
            'user_id' => $user->getId(),
        ]);
    }

    public static function getByDevice(deviceModelObj $device, int $charger_id): ?charging_now_dataModelObj
    {
        return self::model()->findOne([
            'device_id' => $device->getId(),
            'charger_id' => $charger_id,
        ]);
    }

    public static function countByUser(userModelObj $user): int
    {
        return self::query([
            'user_id' => $user->getId(),
        ])->count();
    }

    public static function getAllByUser(userModelObj $user)
    {
        return self::query([
            'user_id' => $user->getId(),
        ])->findAll();
    }

    public static function removeAllByDevice(deviceModelObj $device): bool
    {
        return self::model()->delete([
            'device_id' => $device->getId(),
        ]);
    }
}