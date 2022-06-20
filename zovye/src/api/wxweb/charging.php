<?php

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\ChargingServ;
use zovye\Device;
use zovye\Group;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;
use zovye\Order;
use zovye\request;
use zovye\State;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\error;
use function zovye\is_error;
use function zovye\isEmptyArray;

class charging
{
    public static function groupList(): array
    {
        $query = Group::query(Group::CHARGING);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $lng = request::float('lng');
        $lat = request::float('lat');

        if ($lng > 0 && $lat > 0) {
            $distanceFN = function($loc) use($lng, $lat) {
                $res = Util::getDistance($loc, ['lng' => $lng, 'lat' => $lat], 'driving');
                return is_error($res) ? 0 : $res;
            };     
        } else {
            $distanceFN = function() { return 0;};
        }

        $orderby = sprintf("st_distance_sphere(POINT(%f,%f),loc) asc", $lng, $lat);
        $query->orderBy($orderby);

        //列表数据
        $query->page($page, $page_size);

        $result = [];
        /** @var device_groupsModelObj $group */
        foreach ($query->findAll() as $group) {
            $data = $group->format(false);
            if (!isEmptyArray($data['loc'])) {
                $data['distance'] = $distanceFN($data['loc']);
            }
            $data['devices'] = [
                'total' => Device::query(['group_id' => $group->getId()])->count(),
            ];
            $result[] = $data;
        }

        return $result;
    }

    public static function groupDetail(): array
    {
        $id = request::int('id');
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            return err('找不到指定的分组信息！');
        }

        $group_data = $group->format();

        $lng = request::float('lng');
        $lat = request::float('lat');

        $res = Util::getDistance($group_data['loc'], ['lng' => $lng, 'lat' => $lat], 'driving');
        $group_data['distance'] = is_error($res) ? 0 : $res;

        $group_data['devices'] = [
            'total' => Device::query(['group_id' => $group->getId()])->count(),
        ];

        return $group_data;
    }

    public static function deviceList()
    {
        $id = request::int('id');
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            return err('找不到指定的分组信息！');
        }

        $query = Device::query(['group_id' => $group->getId()]);

        $list = [];
        /** @var deviceModelObj $device */
        foreach ($query->findAll() as $device) {
            if (!$device->isChargingDevice()) {
                continue;
            }

            $data = array_merge($device->profile(), $device->getChargingData());
            $data['online'] = $device->isMcbOnline();
            $data['charger'] = [];
            $chargerNum = $device->getChargerNum();
            for ($i = 0; $i < $chargerNum; $i++) {
                $data['charger'][] = $device->getChargerData($i + 1);
            }

            $list[] = $data;
        }

        return $list;
    }

    public static function start()
    {
        $user = common::getUser();

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = request::int('chargerID');

        $data = $device->getChargerData($chargerID);
        if (empty($data)) {
            return err('充电枪不存在！');
        }

        $group = $device->getGroup();
        if (empty($group)) {
            return err('没有计费设置，请联系管理员！');
        }

        if (!$device->isMcbOnline(false)) {
            return err('设备离线，请稍后再试！');
        }

        if (!$device->lockAcquire()) {
            return err('设备正忙，请稍后再试！');
        }

        $device_charging_data = $device->settings("extra.charging.$chargerID", []);
        if ($device_charging_data && $device_charging_data['user'] != $user->getId()) {
            return err('设备正忙，请稍后再试！');
        }

        if (Order::exists($device_charging_data['serial'])) {
            return err('正在充电中！');
        }

        if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
            return err('用户锁定失败，请稍后再试！');
        }

        $user_charging_data = $user->settings('extra.charging', []);
        if ($user_charging_data && $user_charging_data['device'] != $device->getId()) {
            return err('用户卡正在使用中，请稍后再试！');
        }

        $total = $user->getCommissionBalance()->total();
        if ($total < 1) {
            return err('用户卡余额不足！');
        }

        if ($device_charging_data || $user_charging_data) {
            $serial = $device_charging_data ? $device_charging_data['serial'] : $user_charging_data['serial'];
            if (!$device->mcbNotify('run', '', [
                'ser' => $serial,
                'ch' => $chargerID,
                'timeout' => 60,
                'cardNO' => $user->getPhysicalCardNO(),
                'balance' => $total,
            ])) {
                return err('设备通信失败！');
            }
            
            return '已通知设备开启！';
        }

        $serial = $device->getChargingSerial($chargerID);

        $order_data = [
            'src' => Order::CHARGING,
            'order_id' => $serial,
            'openid' => $user->getOpenid(),
            'agent_id' => $device->getAgentId(),
            'device_id' => $device->getId(),
            'name' => $group->getName(),
            'goods_id' => $group->getId(),
            'num' => 1,
            'price' => -1,
            'account' => empty($acc) ? '' : $acc->name(),
            'ip' => Util::getClientIp(),
            'extra' => [
                'group' => $group->profile(),
                'device' => [
                    'imei' => $device->getImei(),
                    'name' => $device->getName(),
                ],
                'user' => $user->profile(),
                'card' => $user->getPhysicalCardNO(),
            ],
        ];

        $agent = $device->getAgent();
        if ($agent) {
            $order_data['extra']['agent'] = $agent->profile();
        }

        $order = Order::create($order_data);
        if (empty($order)) {
            return err('创建订单失败！');
        }

        if (!$user->updateSettings('extra.charging', [
            'serial' => $serial,
            'device' => $device->getId(),
            'chargerID' => $chargerID,
            'time' => TIMESTAMP,
        ])) {
            return err('保存数据失败！');
        }

        if (!$device->updateSettings("extra.charging.$chargerID", [
            'serial' => $serial,
            'user' => $user->getId(),
            'time' => TIMESTAMP,
        ])) {
            return err('保存数据失败！');
        }

        if (!$device->mcbNotify('run', '', [
            'ser' => $serial,
            'ch' => $chargerID,
            'timeout' => 60,
            'card' => $user->getPhysicalCardNO(),
            'balance' => $total,
        ])) {
            return err('设备通信失败！');
        }

        return '已通知设备开启，请及时插入充电枪！';
    }

    public static function stop()
    {
        $user = common::getUser();

        if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
            return err('用户锁定失败，请稍后再试！');
        }

        $last_charging_data = $user->settings('extra.charging', []);

        if (isEmptyArray($last_charging_data)) {
            return err('没有发现正在充电的设备！');
        }

        $device = Device::get($last_charging_data['device']);
        if (empty($device)) {
            return err('设备不存在！');
        }

        if (!$device->lockAcquire()) {
            return err('设备正忙，请稍后再试！');
        }

        $chargerID = $last_charging_data['chargerID'];

        $last_charging_data = $device->settings("extra.charging.$chargerID", []);
        if ($last_charging_data && $last_charging_data['user'] != $user->getId()) {
            return err('其他用户正在使用当前设备！');
        }

        if ($device->mcbNotify('config', '', [
            "req" => "stop",
            "ch" => $chargerID,
            "ser" => $last_charging_data['serial'],
        ])) {
            return err('设备通信失败，请重试！');
        }

        return '已通知设备停止，请稍候！';
    }

    public static function stats()
    {
        $user = common::getUser();

        if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
            return err('用户锁定失败，请稍后再试！');
        }

        $last_charging_data = $user->settings('extra.charging', []);

        if (isEmptyArray($last_charging_data)) {
            return err('没有发现正在充电的设备！');
        }

        $serial = $last_charging_data['serial'];

        $order = Order::get($serial, true);
        if (empty($order)) {
            return err('订单不存在！');
        }

        $result = $order->getExtraData('charging.record', []);
        if (empty($result)) {
            $result = ChargingServ::getChargingResult($serial);
            if (is_error($result)) {
                return $result;
            }

            $order->setExtraData('charging.record', $result);
            $order->setPrice($result['totalPrice'] * 100);
            $order->save();
        }

        return $result;
    }

}