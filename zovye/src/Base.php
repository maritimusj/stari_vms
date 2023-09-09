<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use zovye\model\base\modelFactory;
use zovye\model\base\modelObjFinder;

class Base
{
    public static function model(): modelFactory
    {
        trigger_error('Base::model not implemented', E_USER_ERROR);
    }

    public static function create($data)
    {
        if (is_callable($data)) {
            $data = call_user_func($data);
        }

        if (!is_array($data)) {
            trigger_error('data is not an array', E_USER_ERROR);
        }

        $classname = static::model()->objClassname();

        if (property_exists($classname, 'uniacid')) {
            $data['uniacid'] = We7::uniacid();
        }

        if (property_exists($classname, 'extra') && isset($data['extra'])) {
            $data['extra'] = call_user_func([$classname, 'serializeExtra'], $data['extra']);
        }

        return static::model()->create($data);
    }

    public static function query($condition): modelObjFinder
    {
        return self::model()->query($condition);
    }

    public static function findOne($condition)
    {
        return self::model()->findOne($condition);
    }

    public static function exists($condition): bool
    {
        return self::model()->exists($condition);
    }

    public static function remove($condition): bool
    {
        return self::model()->remove($condition);
    }
}