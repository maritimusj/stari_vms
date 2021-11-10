<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\packageModelObj;

class Package
{
    /**
     * @param array $data
     * @return packageModelObj|null
     */
    public static function create(array $data = []): ?packageModelObj
    {
        return m('package')->create($data);
    }

    /**
     * @param mixed $condition
     * @return base\modelObjFinder
     */
    public static function query($condition = []): base\modelObjFinder
    {
        return m('package')->query($condition);
    }

    /**
     * @param $id
     * @return packageModelObj|null
     */
    public static function get($id): ?packageModelObj
    {
        return self::findOne(['id' => $id]);
    }

    /**
     * @param mixed $condition
     * @return packageModelObj|null
     */
    public static function findOne($condition = []): ?packageModelObj
    {
        return self::query($condition)->findOne();
    }
}