<?php


namespace zovye;

use zovye\base\model;
use zovye\base\modelObj;
use zovye\model\userModelObj;
use zovye\base\modelObjFinder;
use zovye\model\deviceModelObj;
use zovye\model\storageModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class Storage
{
    public static function create($data = []): ?storageModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('storage')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('storage')->create($data);
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('storage')->where(We7::uniacid([]))->where($condition);
    }

    public static function get($id): ?storageModelObj
    {
        return self::findOne(['id' => $id]);
    }

    /**
     * @param $cond
     * @return storageModelObj|null
     */
    public static function findOne($cond): ?storageModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($v, $name = 'default'): bool
    {
        if (is_array($v)) {
            $cond = $v;
        } elseif (is_string($v)) {
            $cond = ['uid' => $v];
        } elseif ($v instanceof modelObj) {
            $cond = ['uid' => self::getUID($v, $name)];
        }
        return self::query()->exists($cond);
    }

    public static function find($obj, $name = 'default'): ?storageModelObj 
    {
        $uid = self::getUID($obj, $name);
        return self::findOne(['uid' => $uid]);
    }

    /**
     * 获取指定对象指定名称的仓库UID
     */
    public static function getUID(modelObj $obj, $name = 'default'): string
    {
        if (empty($name)) {
            $name = 'default';
        }

        if ($obj instanceof userModelObj) {
            return "user:{$obj->getId()}:{$name}";
        }
        if ($obj instanceof deviceModelObj) {
            return "device:{$obj->getImei()}:{$name}";
        }
        return "obj:{$obj->getId()}:{$name}";
    }


    public static function for(modelObj $obj, $name = 'default'): ?storageModelObj
    {
        if (self::exists($obj, $name)) {
            return self::find($obj, $name);
        }
    }
}