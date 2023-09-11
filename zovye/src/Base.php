<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use RuntimeException;
use zovye\base\modelFactory;
use zovye\base\modelObjFinder;

class Base
{
    public static function model(): modelFactory
    {
        throw new RuntimeException('Base::model not implemented');
    }

    protected static function has($property): bool
    {
        return property_exists(static::model()->objClassname(), $property);
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

        if (self::has('uniacid')) {
            $data['uniacid'] = We7::uniacid();
        }

        if (self::has('extra') && isset($data['extra'])) {
            $data['extra'] = call_user_func([$classname, 'serializeExtra'], $data['extra']);
        }

        return static::model()->create($data);
    }

    public static function query($condition = []): modelObjFinder
    {
        if (self::has('uniacid')) {
            return static::model()->where(We7::uniacid([]))->where($condition);
        }

        return static::model()->query($condition);
    }

    public static function findOne($condition = [])
    {
        return self::query($condition)->findOne();
    }

    public static function exists($condition = []): bool
    {
        return self::query($condition)->exists();
    }

    public static function remove($condition): bool
    {
        return self::query($condition)->delete();
    }
}