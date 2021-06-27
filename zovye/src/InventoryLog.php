<?php


namespace zovye;


use zovye\base\modelObjFinder;
use zovye\model\inventoryModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class InventoryLog
{
    public function create($data = []): ?inventoryModelObj
    {
        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('inventory_log')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('inventory_log')->create($data);
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('inventory_log')->where($condition);
    }

    /**
     * @param $cond
     * @return inventoryModelObj|null
     */
    public static function findOne($cond): ?inventoryModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($cond): bool
    {
        return self::query()->exists($cond);
    }
}