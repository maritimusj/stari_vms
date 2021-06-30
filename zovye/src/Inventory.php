<?php


namespace zovye;

use zovye\base\modelObj;
use zovye\model\userModelObj;
use zovye\base\modelObjFinder;
use zovye\model\deviceModelObj;
use zovye\model\inventoryModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class Inventory
{
    public static function create($data = []): ?inventoryModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('inventory')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('inventory')->create($data);
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('inventory')->where(We7::uniacid([]))->where($condition);
    }

    public static function get($id): ?inventoryModelObj
    {
        return self::findOne(['id' => $id]);
    }

    /**
     * @param $cond
     * @return inventoryModelObj|null
     */
    public static function findOne($cond): ?inventoryModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($v): bool
    {
        if (is_array($v)) {
            $cond = $v;
        } elseif (is_string($v)) {
            $cond = ['uid' => $v];
        } elseif ($v instanceof modelObj) {
            $cond = ['uid' => self::getUID($v)];
        } else {
            return false;
        }
        return self::query()->exists($cond);
    }

    public static function find($obj): ?inventoryModelObj
    {
        $uid = self::getUID($obj);
        return self::findOne(['uid' => $uid]);
    }

    /**
     * 获取指定对象指定名称的仓库UID
     * @param modelObj $obj
     * @param string $name
     * @return string
     */
    public static function getUID(modelObj $obj): string
    {
        if ($obj instanceof userModelObj) {
            return "user:{$obj->getId()}:default";
        }
        if ($obj instanceof deviceModelObj) {
            return "device:{$obj->getImei()}:default";
        }
        return "obj:{$obj->getId()}:default";
    }

    public static function for(modelObj $obj): ?inventoryModelObj
    {
        $inventory = self::find($obj);
        if ($inventory) {
            return $inventory;
        }
        $title = '<未命名>';
        if ($obj instanceof userModelObj) {
            $title = "{$obj->getName()}的仓库";
        } elseif ($obj instanceof deviceModelObj) {
            $title = "设备:{$obj->getImei()}";
        }
        return self::create([
            'uid' => Inventory::getUID($obj),
            'title' => $title,
        ]);
    }
}