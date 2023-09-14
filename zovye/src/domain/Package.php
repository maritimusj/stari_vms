<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base;
use zovye\model\packageModelObj;
use function zovye\m;

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
     */
    public static function query($condition = []): base\ModelObjFinder
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