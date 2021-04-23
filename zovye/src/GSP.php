<?php


namespace zovye;


use zovye\model\agentModelObj;
use zovye\model\gsp_userModelObj;
use zovye\model\userModelObj;

class GSP
{
    const REL = 'rel';
    const FREE = 'free';
    const MIXED = 'mixed';

    const LEVEL1 = '[level1]';
    const LEVEL2 = '[level2]';
    const LEVEL3 = '[level3]';

    public static function query($condition = []): base\modelObjFinder
    {
        return m('gsp_user')->query($condition);
    }

    public static function findOne($condition = [])
    {
        return self::query($condition)->findOne();
    }

    public static function from(agentModelObj $agent): base\modelObjFinder
    {
        return self::query(['agent_id' => $agent->getId()]);
    }

    public static function create($data = [])
    {
        return m('gsp_user')->create($data);
    }

    public static function update($condition = [], $data = []): ?bool
    {
        $one = self::findOne($condition);
        if ($one) {
            foreach ($data as $key => $val) {
                $setter = 'set' . ucfirst(toCamelCase($key));
                $one->$setter($val);
            }
            return $one->save();
        }
        return self::create($data) ? true : false;
    }

    public static function getUser(agentModelObj $agent, gsp_userModelObj $obj): ?userModelObj
    {
        switch ($obj->getUid()) {
            case self::LEVEL1:
                return $agent->getSuperior();
            case self::LEVEL2:
                $superior = $agent->getSuperior();
                if ($superior) {
                    return $superior->getSuperior();
                }
                return null;
            case self::LEVEL3:
                $superior = $agent->getSuperior();
                if ($superior) {
                    $superior = $superior->getSuperior();
                    if ($superior) {
                        return $superior->getSuperior();
                    }
                }
                return null;
            default:
                return User::get($obj->getUid(), true);
        }
    }

}