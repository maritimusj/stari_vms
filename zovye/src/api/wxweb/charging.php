<?php

namespace zovye\api\wxweb;

use zovye\api\wx\common;
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

class charging
{
    public static function groupList(): array
    {
        $query = Group::query(Group::CHARGING);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $result = [];
        /** @var device_groupsModelObj $group */
        foreach ($query->findAll() as $group) {
            $result[] = $group->format();
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

        $result = [
            'group' => $group->format(),
        ];

        $query = Device::query(['group_id' => $group->getId()]);

        /** @var deviceModelObj $device */
        foreach ($query->findAll() as $device) {
            if (!$device->isChargingDevice()) {
                continue;
            }

            $data = array_merge($device->profile(), $device->getChargingData());
            $data['charger'] = [];
            $chargerNum = $device->getChargerNum();
            for ($i = 0; $i < $chargerNum; $i++) {
                $data['charger'] = $device->getChargerData($i);
            }
            $result[] = $data;
        }

        return $result;
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

        $total = $user->getCommissionBalance()->total();
        if ($total < 1) {
            return err('用户卡余额不足！');
        }

        $locker = $user->acquireLocker(User::CHARGING_LOCKER);
        if (empty($locker)) {
            return err('用户锁定失败，请稍后再试！');
        }

        $last_serial = $user->settings('extra.charging.serial', '');
        if ($last_serial) {
            return err('用户卡正在使用中，请稍后再试！');
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

    public static function stop()
    {
        $user = common::getUser();

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $locker = $user->acquireLocker(User::CHARGING_LOCKER);
        if (empty($locker)) {
            return err('用户锁定失败，请稍后再试！');
        }

        $last_serial = $user->settings('extra.charging.serial', '');

        if ($device->mcbNotify('config', '', [
            "req" => "stop",
            "ch" => request::int('chargerID'),
            "ser" => $last_serial,
        ])) {
            return err('设备通信失败，请重试！');
        }

        return '已通知设备停止';
    }
}