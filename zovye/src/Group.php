<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\device_groupsModelObj;

class Group
{
    const NORMAL = 0;
    const CHARGING = 1;

    /**
     * @param array $data
     * @return mixed
     */
    public static function create(array $data = [])
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }
        if (empty($data['createtime'])) {
            $data['createtime'] = time();
        }

        $data['extra'] = json_encode($data['extra']);

        return m('device_groups')->create($data);
    }

    /**
     * @param mixed $condition
     * @return base\modelObjFinder
     */
    public static function query($condition = []): base\modelObjFinder
    {
        if (is_numeric($condition)) {
            $condition = ['type_id' => $condition];
        }

        return m('device_groups')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param $id
     * @param int $type_id
     * @return device_groupsModelObj|null
     */
    public static function get($id, int $type_id = self::NORMAL): ?device_groupsModelObj
    {
        static $cache = [];
        if ($id) {
            if (isset($cache[$id])) {
                return $cache[$id];
            }
            $res = self::findOne(['id' => $id], $type_id);
            if ($res) {
                $cache[$res->getId()] = $res;
                return $res;
            }
        }

        return null;
    }

    public static function findOne($condition = [], $type_id = self::NORMAL): ?device_groupsModelObj
    {
        $condition['type_id'] = $type_id;
        return self::query($condition)->findOne();
    }

    public static function format(device_groupsModelObj $entry): array
    {
        $data = [
            'id' => intval($entry->getId()),
            'agentId' => intval($entry->getAgentId()),
            'title' => $entry->getTitle(),
            'clr' => $entry->getClr(),
            'createtime' => Date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        if ($entry->getTypeId() == Group::CHARGING) {
            $data['name'] = $entry->getName();
            $data['description'] = $entry->getDescription();
            $data['address'] = $entry->getAddress();
            $data['loc'] = $entry->getLoc();
        }

        return $data;
    }
}