<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

class Principal
{
    const Agent = 1;
    const Partner = 2;
    const Keeper = 3;
    const Gspor = 4;
    const Tester = 5;

    public static function update(userModelObj $user): bool
    {
        $updateDataFN = function ($cond, $user) {
            $obj = self::findOne($cond);
            if ($obj) {
                $obj->setName($user->getName());
                return $obj->save();
            }
            return false;
        };

        $createFN = function ($data, $user) {
            $data['name'] = $user->getName();
            return self::create($data);
        };

        $data = [
            'user_id' => $user->getId(),
        ];

        $v = [
            self::Agent => 'isAgent',
            self::Partner => 'isPartner',
            self::Keeper => 'isKeeper',
            self::Gspor => 'isGspor',
            self::Tester => 'isTester',
        ];

        foreach ($v as $principal_id => $fn) {
            $data['principal_id'] = $principal_id;
            if ($user->$fn()) {
                if (self::exists($data)) {
                    if (!$updateDataFN($data, $user)) {
                        return false;
                    }
                } elseif (!$createFN($data, $user)) {
                    return false;
                }
            } else {
                self::delete($data);
            }
        }

        return true;
    }

    public static function create($data)
    {
        return m('principal')->create($data);
    }

    public static function findOne($condition = [])
    {
        return m('principal')->findOne($condition);
    }

    public static function exists($condition = []): bool
    {
        return m('principal')->exists($condition);
    }

    public static function delete($condition = []): bool
    {
        return m('principal')->delete($condition);
    }

    public static function agent($condition = []): base\modelObjFinder
    {
        return m('agent_vw')->where(We7::uniacid([]))->where($condition);
    }

    public static function partner($condition = []): base\modelObjFinder
    {
        return m('partner_vw')->where(We7::uniacid([]))->where($condition);
    }

    public static function keeper($condition = []): base\modelObjFinder
    {
        return m('keeper_vw')->where(We7::uniacid([]))->where($condition);
    }

    public static function gspsor($condition = []): base\modelObjFinder
    {
        return m('gspor_vw')->where(We7::uniacid([]))->where($condition);
    }

    public static function tester($condition = []): base\modelObjFinder
    {
        return m('tester_vw')->where(We7::uniacid([]))->where($condition);
    }

}