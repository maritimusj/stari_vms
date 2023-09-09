<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\goods_expire_alertModelObj;

class GoodsExpireAlert
{
    public static function model(): model\base\modelFactory
    {
        return m('goods_expire_alert');
    }

    public static function create($data = []): ?goods_expire_alertModelObj
    {
        if (isset($data['extra'])) {
            $data['extra'] = json_encode($data['extra']);
        }
        return self::model()->create($data);
    }

    public static function query($condition = []): model\base\modelObjFinder
    {
        return self::model()->where($condition);
    }

    public static function findOne($condition = [])
    {
        return self::query($condition)->findOne();
    }

    public static function delete($condition = []): bool
    {
        return self::model()->delete($condition);
    }

    public static function getFor(deviceModelObj $device, int $index, $goods_id = 0, $agent_restrict = true): ?goods_expire_alertModelObj
    {
        $condition = [
            'device_id' => $device->getId(),
            'lane_id' => $index,
        ];

        if ($goods_id > 0) {
            $condition['goods_id'] = $goods_id;
        }

        if ($agent_restrict) {
            $agent = $device->getAgent();
            if ($agent) {
                $condition['agent_id'] = $agent->getId();
            }
        }

        return self::findOne($condition);
    }
}