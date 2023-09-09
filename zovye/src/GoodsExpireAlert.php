<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\goods_expire_alertModelObj;

class GoodsExpireAlert extends Base
{
    public static function model(): base\modelFactory
    {
        return m('goods_expire_alert');
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