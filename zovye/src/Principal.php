<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\principalModelObj;
use zovye\model\userModelObj;

class Principal
{
    const Admin = 100;

    const Agent = 1;
    const Partner = 2;
    const Keeper = 3;
    const Gspor = 4;
    const Tester = 5;

    const Promoter = 7;

    public static function create($data): ?principalModelObj
    {
        if ($data['extra']) {
            $data['extra'] = principalModelObj::serializeExtra($data['extra']);
        }
        return m('principal')->create($data);
    }

    public static function findOne($condition = []): ?principalModelObj
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

    public static function is(userModelObj $user, $id): bool
    {
        return m('principal')->exists([
            'user_id' => $user->getId(),
            'principal_id' => $id,
        ]);
    }
    public static function admin($condition = []): base\modelObjFinder
    {
        return m('admin_vw')->where(We7::uniacid([]))->where($condition);
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

    public static function gspor($condition = []): base\modelObjFinder
    {
        return m('gspor_vw')->where(We7::uniacid([]))->where($condition);
    }

    public static function tester($condition = []): base\modelObjFinder
    {
        return m('tester_vw')->where(We7::uniacid([]))->where($condition);
    }

    public static function promoter($condition = []): base\modelObjFinder
    {
        return m('promoter_vw')->where(We7::uniacid([]))->where($condition);
    }
}