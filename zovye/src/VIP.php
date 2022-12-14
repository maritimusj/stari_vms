<?php

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\agentModelObj;
use zovye\model\userModelObj;
use zovye\model\vipModelObj;

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

    public static function query($condition = []): modelObjFinder
    {
        return m('vip')->where(We7::uniacid([]))->where($condition);
    }

    public static function findOne($condition = []): ?vipModelObj
    {
        return self::query($condition)->findOne();
    }

    public static function getFor(agentModelObj $agent, userModelObj $user): bool
    {
        return self::query(['agent_id' => $agent->getId(), 'user_id' => $user->getId()])->findOne();
    }

    public static function getByMobile(agentModelObj $agent, string $mobile): bool
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

    public static function addUser(agentModelObj $agent, userModelObj $user): vipModelObj
    {
        return self::create([
            'agent_id' => $agent->getId(),
            'user_id' => $user->getId(),
            'mobile' => $user->getMobile(),
        ]);
    }

    public static function addMobile(agentModelObj $agent, string $mobile): vipModelObj
    {
        return self::create([
            'agent_id' => $agent->getId(),
            'mobile' => $mobile,
        ]);
    }
}