<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\base\modelObj;
use zovye\model\component_userModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class ComponentUser
{
    public static function create($data = [])
    {
        if (!isset($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('order')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

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


    public static function findOne($condition = [])
    {
        return self::query($condition)->findOne();
    }

    public static function removeAll($condition = [])
    {
        We7::pdo_delete(component_userModelObj::getTableName(modelObj::OP_WRITE), We7::uniacid($condition));
    }
}