<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base\ModelObj;
use zovye\base\ModelObjFinder;
use zovye\model\deviceModelObj;
use zovye\model\inventoryModelObj;
use zovye\model\userModelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\util\Util;
use zovye\We7;
use function zovye\err;
use function zovye\m;
use function zovye\settings;

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
     * @return ModelObjFinder
     */
    public static function query(array $condition = []): ModelObjFinder
    {
        if (is_array($condition) && isset($condition['id'])) {
            return m('inventory')->where($condition);
        }

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
        } elseif ($v instanceof ModelObj) {
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
     * @param ModelObj $obj
     * @return string
     */
    public static function getUID(ModelObj $obj): string
    {
        if ($obj instanceof userModelObj) {
            return "user:{$obj->getId()}:default";
        }
        if ($obj instanceof deviceModelObj) {
            return "device:{$obj->getImei()}:default";
        }

        return "obj:{$obj->getId()}:default";
    }

    public static function for(ModelObj $obj): ?inventoryModelObj
    {
        $inventory = self::find($obj);
        if ($inventory) {
            return $inventory;
        }
        if ($obj instanceof userModelObj) {
            $title = "{$obj->getName()}";
            $extra = ['user' => $obj->profile()];
        } elseif ($obj instanceof deviceModelObj) {
            $title = "{$obj->getImei()}";
            $extra = ['device' => $obj->profile()];
        } else {
            return null;
        }

        return self::create([
            'uid' => Inventory::getUID($obj),
            'title' => $title,
            'extra' => $extra,
        ]);
    }

    public static function syncDevicePayloadLog(userModelObj $user, deviceModelObj $device, array $result, $memo = '')
    {
        $inventory = self::for($user);
        if (empty($inventory)) {
            return err('打开用户仓库失败！');
        }
        if (!$inventory->acquireLocker()) {
            return err('无法锁定用户仓库！');
        }
        $goods_lack = !settings('inventory.goods.mode');
        $clr = Util::randColor();
        foreach ($result as $entry) {
            if (empty($entry['goodsId'])) {
                return err('请检查商品设置是否正确！');
            }
            if ($entry['num'] > 0) {
                if (!$goods_lack) {
                    $goods = $inventory->getGoods($entry['goodsId']);
                    if (empty($goods) || $goods->getNum() < $entry['num']) {
                        return err('用户仓库商品库存不足！');
                    }
                }
            }
            if ($entry['num'] != 0) {
                $log = $inventory->stock(null, intval($entry['goodsId']), -$entry['num'], [
                    'memo' => $memo,
                    'device' => $device->profile(),
                    'clr' => $clr,
                    'serial' => REQUEST_ID,
                ]);
                if (empty($log)) {
                    return err('仓库商品操作失败！');
                }
            }
        }

        return true;
    }
}