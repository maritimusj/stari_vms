<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\business;

use zovye\base\ModelObjFinder;
use zovye\Config;
use zovye\model\agentModelObj;
use zovye\model\userModelObj;
use zovye\model\vipModelObj;
use zovye\We7;
use function zovye\m;

class VIP
{
    public static function create(array $data = []): ?vipModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        return m('vip')->create($data);
    }

    public static function get($id): ?vipModelObj
    {
        return m('vip')->findOne(['id' => intval($id)]);
    }

    public static function query($condition = []): ModelObjFinder
    {
        return m('vip')->where(We7::uniacid([]))->where($condition);
    }

    public static function findOne($condition = []): ?vipModelObj
    {
        return self::query($condition)->findOne();
    }

    public static function getFor(agentModelObj $agent, userModelObj $user): ?vipModelObj
    {
        return self::query(['agent_id' => $agent->getId(), 'user_id' => $user->getId()])->findOne();
    }

    public static function getByMobile(agentModelObj $agent, string $mobile): ?vipModelObj
    {
        return self::query(['agent_id' => $agent->getId(), 'mobile' => $mobile])->findOne();
    }

    public static function exists(agentModelObj $agent, userModelObj $user): bool
    {
        return self::query(['agent_id' => $agent->getId(), 'user_id' => $user->getId()])->exists();
    }

    public static function existsByMobile(agentModelObj $agent, string $mobile): bool
    {
        return self::query(['agent_id' => $agent->getId(), 'mobile' => $mobile])->exists();
    }

    public static function remove(agentModelObj $agent, userModelObj $user): bool
    {
        return m('vip')->delete([
                'agent_id' => $agent->getId(),
                'user_id' => $user->getId(),
            ]) && m('vip')->delete([
                'agent_id' => $agent->getId(),
                'mobile' => $user->getMobile(),
            ]);
    }

    public static function removeByUserId(agentModelObj $agent, int $user_id): bool
    {
        return m('vip')->delete([
            'agent_id' => $agent->getId(),
            'user_id' => $user_id,
        ]);
    }

    public static function removeByMobile(agentModelObj $agent, string $mobile): bool
    {
        return m('vip')->delete([
            'agent_id' => $agent->getId(),
            'mobile' => $mobile,
        ]);
    }

    public static function removeAll(agentModelObj $agent): bool
    {
        return m('vip')->delete([
            'agent_id' => $agent->getId(),
        ]);
    }

    public static function addUser(agentModelObj $agent, userModelObj $user, string $name): ?vipModelObj
    {
        return self::create([
            'agent_id' => $agent->getId(),
            'user_id' => $user->getId(),
            'name' => empty($name) ? $user->getName() : $name,
            'mobile' => $user->getMobile(),
        ]);
    }

    public static function addMobile(agentModelObj $agent, string $name, string $mobile): ?vipModelObj
    {
        return self::create([
            'agent_id' => $agent->getId(),
            'name' => $name,
            'mobile' => $mobile,
        ]);
    }

    public static function getRechargePromotionVal(int $amount): int
    {
        $promotion = Config::fueling('vip.recharge.promotion', []);
        if (empty($promotion) || !$promotion['enabled'] || empty($promotion['list'])) {
            return $amount;
        }

        $list = (array)$promotion['list'];

        $min_val = 0;
        $last_val = 0;

        foreach ($list as $item) {
            if (empty($item['base']) || empty($item['val'])) {
                continue;
            }

            $base = intval(round($item['base'] * 100));
            $val = intval(round($item['val'] * 100));

            if ($base > $amount) {
                continue;
            }

            if ($min_val == 0 || $amount - $base < $min_val) {
                $last_val = $val;
                $min_val = $amount - $base;
            }
        }

        return $last_val;
    }
}