<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base\ModelObjFinder;
use zovye\model\inventory_logModelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\m;

class InventoryLog
{
    public static function create($data = []): ?inventory_logModelObj
    {
        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('inventory_log')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('inventory_log')->create($data);
    }

    /**
     * @param array $condition
     * @return ModelObjFinder
     */
    public static function query(array $condition = []): ModelObjFinder
    {
        return m('inventory_log')->where($condition);
    }

    /**
     * @param $cond
     * @return inventory_logModelObj|null
     */
    public static function findOne($cond): ?inventory_logModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($cond): bool
    {
        return self::query()->exists($cond);
    }
}