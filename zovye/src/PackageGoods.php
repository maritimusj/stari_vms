<?php

namespace zovye;

use zovye\model\package_goodsModelObj;
use zovye\model\packageModelObj;

class PackageGoods
{
    /**
     * @param array $data
     * @return package_goodsModelObj|null
     */
    public static function create(array $data = []): ?package_goodsModelObj
    {
        return m('package_goods')->create($data);
    }

    /**
     * @param mixed $condition
     * @return base\modelObjFinder
     */
    public static function query($condition = []): base\modelObjFinder
    {
        return m('package_goods')->query($condition);
    }

    public static function queryFor(packageModelObj $package): base\modelObjFinder
    {
        return self::query(['package_id' => $package->getId()]);
    }

    /**
     * @param $id
     * @return package_goodsModelObj|null
     */
    public static function get($id): ?package_goodsModelObj
    {
        return self::findOne(['id' => $id]);
    }

    /**
     * @param mixed $condition
     * @return package_goodsModelObj|null
     */
    public static function findOne($condition = []): ?package_goodsModelObj
    {
        return self::query($condition)->findOne();
    }

}