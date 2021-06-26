<?php


namespace zovye;


use zovye\base\modelObjFinder;
use zovye\model\storageModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class StorageGoods
{
    public function create($data = []): ?storageModelObj
    {
        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('storage_log')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('storage_goods')->create($data);
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('storage_goods')->where($condition);
    }

    /**
     * @param $cond
     * @return storageModelObj|null
     */
    public static function findOne($cond): ?storageModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($cond): bool
    {
        return self::query()->exists($cond);
    }
}