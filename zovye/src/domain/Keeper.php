<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base;
use zovye\base\ModelObjFinder;
use zovye\model\deviceModelObj;
use zovye\model\keeperModelObj;
use zovye\model\replenishModelObj;
use zovye\model\userModelObj;
use zovye\We7;
use function zovye\m;

class Keeper
{
    const VIEW = 0;
    const OP = 1;

    //订单生成时分佣
    const COMMISSION_ORDER = 0;
    //补货时分佣
    const COMMISSION_RELOAD = 1;

    private static $cache = [];

    public static function model(): base\ModelFactory
    {
        return m('keeper');
    }

    public static function query($cond = []): ModelObjFinder
    {
        if ($cond['id']) {
            return m('keeper')->where($cond);
        }

        return m('keeper')->query(We7::uniacid([]))->where($cond);
    }

    public static function exists(userModelObj $user): bool
    {
        if (!$user->isWxUser()) {
            return false;
        }

        $mobile = $user->getMobile();
        if (empty($mobile)) {
            return false;
        }

        return m('keeper')->exists(We7::uniacid([
            'mobile' => $mobile,
        ]));
    }

    public static function cache($keeper)
    {
        self::$cache[$keeper->getId()] = $keeper;
    }

    public static function getFromCache($id)
    {
        return self::$cache[$id];
    }

    public static function cacheExists($id): bool
    {
        return isset(self::$cache[$id]);
    }

    /**
     * @param $id
     * @return keeperModelObj|null
     */
    public static function get($id): ?keeperModelObj
    {
        if (self::cacheExists($id)) {
            return self::getFromCache($id);
        }

        $keeper = m('keeper')->findOne(['id' => $id]);
        if ($keeper) {
            self::cache($keeper);

            return $keeper;
        }

        return null;
    }

    public static function findOne(array $cond = []): ?keeperModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function create(array $data = []): ?keeperModelObj
    {
        if (!isset($data['uniacid'])) {
            $data = We7::uniacid($data);
        }

        return m('keeper')->create($data);
    }

    /**
     * @param keeperModelObj $keeper
     * @param deviceModelObj $device
     * @param int $goods_id
     * @param int $original
     * @param int $num
     * @param array $extra
     * @return replenishModelObj|null
     */
    public static function createReplenish(
        keeperModelObj $keeper,
        deviceModelObj $device,
        int $goods_id,
        int $original,
        int $num,
        array $extra = []
    ): ?replenishModelObj {
        return Replenish::create(
            [
                'device_uid' => $device->getImei(),
                'agent_id' => $device->getAgentId(),
                'keeper_id' => $keeper->getId(),
                'goods_id' => $goods_id,
                'org' => $original,
                'num' => $num,
                'extra' => $extra,
            ]
        );
    }
}
