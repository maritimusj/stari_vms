<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\goods_expire_alertModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;

class GoodsExpireAlert extends Base
{
    public static function model(): base\modelFactory
    {
        return m('goods_expire_alert');
    }

    public static function getFor(
        deviceModelObj $device,
        int $index,
        bool $agent_restrict = false
    ): ?goods_expire_alertModelObj {

        $condition = [
            'device_id' => $device->getId(),
            'lane_id' => $index,
        ];

        if ($agent_restrict) {
            $agent = $device->getAgent();
            if ($agent) {
                $condition['agent_id'] = $agent->getId();
            }
        }

        return self::findOne($condition);
    }

    public static function getAllExpiredForAgent(userModelObj $user, $fetch_total = false)
    {
        $query = self::query(['agent_id' => $user->getId()]);
        $query->where('expired_at>0 AND expired_at-pre_days*86400<'.time());

        if ($fetch_total) {
            return $query->count();
        }

        $query->orderBy('expired_at ASC');

        return $query->findAll();
    }

    public static function getAllExpiredForKeeper($user, $fetch_total = false)
    {
        if ($user instanceof userModelObj) {
            $keeper = $user->getKeeper();
        } elseif ($user instanceof keeperModelObj) {
            $keeper = $user;
        } else {
            return [];
        }

        $query = We7::load()->object('query')->from(self::model()->getTableName(), 'a')
            ->leftjoin(Keeper::model()->getTableName(), 'k')
            ->on('a.agent_id', 'k.agent_id')
            ->leftjoin(m('keeper_devices')->getTableName(), 'd')
            ->on('k.id', 'd.keeper_id')
            ->on('d.device_id', 'a.device_id')
            ->select('a.id')
            ->where('k.id', $keeper->getId())
            ->where('d.kind', '1')
            ->where('expired_at>0 AND expired_at-pre_days*86400>'.time());

        if ($fetch_total) {
            return $query->count();
        }

        $all = $query->orderby('expired_at ASC')->getAll();

        return self::query(['id' => $all])->findAll();
    }
}