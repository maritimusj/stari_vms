<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use zovye\model\goods_expire_alertModelObj;

class GoodsExpireAlert
{
    public static function model(): base\modelFactory
    {
        return m('goods_expire_alert');
    }

    public static function create($data = []): ?goods_expire_alertModelObj
    {
        return self::model()->create($data);
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return self::model()->where($condition);
    }

    public static function findOne($condition = [])
    {
        return self::query($condition)->findOne();
    }

    public static function delete($condition = []): bool
    {
        return self::model()->delete($condition);
    }
}