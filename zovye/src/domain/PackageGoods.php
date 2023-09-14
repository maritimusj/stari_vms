<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base;
use zovye\model\package_goodsModelObj;
use zovye\model\packageModelObj;
use function zovye\m;

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
     */
    public static function query($condition = []): base\ModelObjFinder
    {
        return m('package_goods')->query($condition);
    }

    public static function queryFor(packageModelObj $package): base\ModelObjFinder
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