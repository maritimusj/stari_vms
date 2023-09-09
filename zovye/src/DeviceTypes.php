<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\device_typesModelObj;
use zovye\model\deviceModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class DeviceTypes
{
    const UNKNOWN_TYPE = -1;

    public static function getDefault(): ?device_typesModelObj
    {
        $id = settings('device.multi-types.first');
        if ($id) {
            return self::get($id);
        }

        return null;
    }

    /**
     * @param $id
     * @return device_typesModelObj|null
     */
    public static function get($id): ?device_typesModelObj
    {
        /** @var device_typesModelObj[] $cache */
        static $cache = [];

        if ($id) {
            $id = intval($id);
            if ($cache[$id]) {
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

    public static function from(deviceModelObj $device): ?device_typesModelObj
    {
        if (!$device->isCustomizedType()) {
            return self::get($device->getDeviceType());
        }

        $device_type = self::findOne(['device_id' => $device->getId()]);
        if (empty($device_type)) {
            $device_type = self::create([
                'device_id' => $device->getId(),
                'title' => '<自定义>',
                'extra' => [
                    'cargo_lanes' => [
                        [
                            'goods' => 0,
                            'capacity' => 0,
                        ],
                    ],
                ],
            ]);
        }

        return $device_type;
    }

    /**
     * @param mixed $cond
     * @return device_typesModelObj|null
     */
    public static function findOne($cond): ?device_typesModelObj
    {
        return self::query($cond)->findOne();
    }

    /**
     * @param array $data
     * @return device_typesModelObj|null
     */
    public static function create(array $data = []): ?device_typesModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }
        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('device_types')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('device_types')->create($data);
    }

    public static function getList(array $params = []): array
    {
        $page = max(1, intval($params['page']));
        $page_size = empty($params['pagesize']) ? DEFAULT_PAGE_SIZE : intval($params['pagesize']);

        $query = DeviceTypes::query(['device_id' => 0]);

        if (isset($params['agent_id'])) {
            $agent_id = intval($params['agent_id']);
            if (empty($params['platform_types'])) {
                if ($agent_id) {
                    $query->where(['agent_id' => $agent_id]);
                } else {
                    $query->where("(agent_id=0 OR agent_id IS NULL)");
                }

            } else {
                $query->where("(agent_id=$agent_id OR agent_id=0 OR agent_id IS NULL)");
            }
        }

        $keywords = $params['keywords'];
        if ($keywords) {
            $query->where([
                'title LIKE' => "%$keywords%",
            ]);
        }

        $total = $query->count();
        $total_page = ceil($total / $page_size);

        $device_types = [];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id DESC');

            /** @var device_typesModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = self::format($entry, boolval($params['detail']));
                $device_types[] = $data;
            }
        }

        return [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => $total_page,
            'list' => $device_types,
        ];
    }

    /**
     * @param mixed $condition
     * @return modelObjFinder
     */
    public static function query($condition = []): modelObjFinder
    {
        if (is_array($condition) && isset($condition['id'])) {
            return m('device_types')->where($condition);
        }
        return m('device_types')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param device_typesModelObj $entry
     * @param bool $detail
     * @return array
     */
    public static function format(device_typesModelObj $entry, bool $detail = false): array
    {
        $data = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'agentId' => $entry->getAgentId(),
            'deviceId' => $entry->getDeviceId(),
            'cargo_lanes' => self::getCargoLanes($entry, $detail),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        $data['lanes_total'] = count($data['cargo_lanes']);
        if ($detail) {
            $query = Device::query(['device_type' => $entry->getId()]);
            $data['devices_total'] = $query->count();

            $agent = $entry->getAgent();
            if ($agent) {
                $data['agent'] = $agent->profile();
            }
        }

        return $data;
    }

    /**
     * @param device_typesModelObj $entry
     * @param bool $detail
     * @return array
     */
    public static function getCargoLanes(device_typesModelObj $entry, bool $detail = false): array
    {
        $cargo_lanes = $entry->getExtraData('cargo_lanes', []);
        if ($detail) {
            foreach ($cargo_lanes as &$lane) {
                $goods_data = Goods::data($lane['goods']);
                if ($goods_data) {
                    $lane['goods_id'] = $goods_data['id'];
                    $lane['goods_unit_title'] = $goods_data['unit_title'];
                    $lane['goods_name'] = $goods_data['name'];
                    $lane['goods_img'] = Util::toMedia($goods_data['img'], true);
                    $lane['goods_price'] = $goods_data['price'];
                    $lane['goods_price_formatted'] = $goods_data['price_formatted'];

                    $lane[Goods::AllowPay] = $goods_data[Goods::AllowPay];
                    $lane[Goods::AllowFree] = $goods_data[Goods::AllowFree];
                    $lane[Goods::AllowBalance] = $goods_data[Goods::AllowBalance];

                    if ($goods_data['CVMachine.code']) {
                        $lane['CVMachine.code'] = $goods_data['CVMachine.code'];
                    }
                } else {
                    $lane['goods_id'] = 0;
                    $lane['goods_unit_title'] = '';
                    $lane['goods_name'] = '<请选择商品>';
                    $lane['goods_img'] = '';
                }
            }
        }

        return $cargo_lanes;
    }
}
