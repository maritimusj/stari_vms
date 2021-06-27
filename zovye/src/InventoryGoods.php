<?php


namespace zovye;


use zovye\base\modelObjFinder;
use zovye\model\inventoryModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class InventoryGoods
{
    public function create($data = []): ?inventoryModelObj
    {
        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('inventory_goods')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('inventory_goods')->create($data);
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('inventory_goods')->where($condition);
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