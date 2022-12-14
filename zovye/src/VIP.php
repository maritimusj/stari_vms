<?php

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
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

    public static function query($condition = []): modelObjFinder
    {
        return m('vip')->where(We7::uniacid([]))->where($condition);
    }

    public static function findOne($condition = []): ?vipModelObj
    {
        return self::query($condition)->findOne();
    }

    public static function get(agentModelObj $agent, userModelObj $user): bool
    {
        return self::query(['agent_id' => $agent->getId(), 'user_id' => $user->getId()])->findOne();
    }

    public static function exists(agentModelObj $agent, userModelObj $user): bool
    {
        return self::query(['agent_id' => $agent->getId(), 'user_id' => $user->getId()])->exists();
    }
}