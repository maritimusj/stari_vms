<?php

namespace zovye;

use zovye\model\package_goodsModelObj;

class PackageGoods
{
    /**
     * @param array $data
     * @return package_goodsModelObj|null
     */
    public static function create($data = []): ?package_goodsModelObj
    {
        return m('package_goods')->create($data);
    }

    /**
     * @param array $condition
     * @return base\modelObjFinder
     */
    public static function query($condition = []): base\modelObjFinder
    {
        return m('package_goods')->query($condition);
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
     * @param array $condition
     * @return package_goodsModelObj|null
     */
    public static function findOne($condition = []): ?package_goodsModelObj
    {
        return self::query($condition)->findOne();
    }

}