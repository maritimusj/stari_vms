<?php


namespace zovye;


use zovye\base\modelObjFinder;
use zovye\model\inventory_goodsModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class InventoryGoods
{
    public static function create($data = []): ?inventory_goodsModelObj
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
     * @return inventory_goodsModelObj|null
     */
    public static function findOne($cond): ?inventory_goodsModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($cond): bool
    {
        return self::query()->exists($cond);
    }
}