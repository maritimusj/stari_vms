<?php


namespace zovye;


class ComponentUser
{
    public static function create($data = [])
    {
        if (!isset($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        return m('component_user')->create($data);
    }

    public static function exists($condition = []): bool
    {
        return self::query($condition)->exists();
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return m('component_user')->where(We7::uniacid([]))->where($condition);
    }


}