<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\agentModelObj;
use zovye\model\referralModelObj;

class Referral
{
    public static function query($condition): model\base\modelObjFinder
    {
        return m('referral')->query(We7::uniacid())->where($condition);
    }

    public static function get($id)
    {
        return self::query(['id' => $id])->findOne();
    }

    public static function from($code): ?referralModelObj
    {
        return self::findOne([
            'code' => $code,
        ]);
    }

    public static function exists($condition = []): bool
    {
        return self::query($condition)->exists();
    }

    public static function findOne($condition = [])
    {
        return self::query($condition)->findOne();
    }

    public static function create($data = [])
    {
        return m('referral')->create($data);
    }

    public static function getAgent($code): ?agentModelObj
    {
        /** @var referralModelObj $referral */
        $referral = m('referral')->findOne(['code' => $code]);
        if ($referral) {
            return $referral->getAgent();
        }

        return null;
    }
}