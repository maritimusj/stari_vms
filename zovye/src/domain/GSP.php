<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base;
use zovye\model\agentModelObj;
use zovye\model\gsp_userModelObj;
use zovye\model\userModelObj;
use function zovye\m;
use function zovye\toCamelCase;

class GSP
{
    const PERCENT = 'percent'; // 百分比
    const AMOUNT = 'amount'; // 固定金额

    const PERCENT_PER_GOODS = 'percent/goods'; // 百分比 x 商品数量

    const AMOUNT_PER_GOODS = 'amount/goods'; // 固定金额 x 商品数量

    const REL = 'rel'; // 三级分佣
    const FREE = 'free'; // 自收分佣
    const MIXED = 'mixed'; // 混合分佣

    const LEVEL1 = '[level1]';
    const LEVEL2 = '[level2]';
    const LEVEL3 = '[level3]';

    public static function query($condition = []): base\ModelObjFinder
    {
        return m('gsp_user')->query($condition);
    }

    public static function findOne($condition = [])
    {
        return self::query($condition)->findOne();
    }

    public static function from(agentModelObj $agent): base\ModelObjFinder
    {
        return self::query(['agent_id' => $agent->getId()]);
    }

    public static function create($data = [])
    {
        return m('gsp_user')->create($data);
    }

    public static function update($condition = [], $data = []): bool
    {
        $one = self::findOne($condition);
        if ($one) {
            foreach ($data as $key => $val) {
                $setter = 'set'.ucfirst(toCamelCase($key));
                $one->$setter($val);
            }

            return $one->save();
        }

        return !empty(self::create($data));
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