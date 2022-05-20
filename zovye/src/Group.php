<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\device_groupsModelObj;

class Group
{
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

        return m('device_groups')->create($data);
    }

    /**
     * @param mixed $condition
     * @return base\modelObjFinder
     */
    public static function query($condition = []): base\modelObjFinder
    {
        return m('device_groups')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param $id
     * @return device_groupsModelObj|null
     */
    public static function get($id): ?device_groupsModelObj
    {
        static $cache = [];
        if ($id) {
            if (isset($cache[$id])) {
                return $cache[$id];
            }
            $res = self::findOne(['id' => $id]);
            if ($res) {
                $cache[$res->getId()] = $res;

                return $res;
            }
        }

        return null;
    }

    public static function findOne($condition = []): ?device_groupsModelObj
    {
        return self::query($condition)->findOne();
    }

    public static function format(device_groupsModelObj $entry): array
    {
        return [
            'id' => intval($entry->getId()),
            'agentId' => intval($entry->getAgentId()),
            'title' => $entry->getTitle(),
            'clr' => $entry->getClr(),
            'createtime' => Date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
    }
}