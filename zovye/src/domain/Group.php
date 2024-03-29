<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use DateTime;
use zovye\base;
use zovye\base\ModelObjFinder;
use zovye\model\device_groupsModelObj;
use zovye\We7;
use function zovye\isEmptyArray;
use function zovye\m;

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
     * @return ModelObjFinder
     */
    public static function query($condition = []): ModelObjFinder
    {
        if (is_numeric($condition)) {
            $condition = ['type_id' => $condition];
        }

        if (is_array($condition) && isset($condition['id'])) {
            return m('device_groups')->where($condition);
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

    public static function format(device_groupsModelObj $entry, bool $detail = true): array
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
            $data['version'] = $entry->getVersion();

            $fee =  $entry->getFee();
            if ($detail) {
                $data['fee'] = $fee;
            }
            
            if (!isEmptyArray($fee)) {
                $min = 0;
                $max = 0;
                for ($i = 0; $i < 4; $i ++) {
                    $total = floatval($fee["l$i"]['ef'] + $fee["l$i"]['sf']);
                    if ($total > 0 && (empty($min) || $total < $min)) {
                        $min = $total;
                    }
                    if (empty($max) || $total > $max) {
                        $max = $total;
                    }
                }
                if ($max - $min < 0.001) {
                    $data['tips'] = sprintf("¥ %.04f /度", $max);
                } else {
                    $data['tips'] = sprintf("¥ %.04f - %.04f /度", $min, $max);
                }

                $fee = $entry->getFeeAt(new DateTime());
                
                $data['current_ef'] = sprintf("¥ %.04f /度", $fee['ef']);
                $data['current_sf'] .= sprintf("¥ %.04f /度", $fee['sf']);
            }

            $data['bonus'] = $entry->getExtraData('bonus', []);
        }

        return $data;
    }
}