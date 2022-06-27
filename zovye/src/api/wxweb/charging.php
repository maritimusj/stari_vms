<?php

namespace zovye\api\wxweb;

use zovye\api\wx\balance;
use zovye\api\wx\common;
use zovye\App;
use zovye\Charging as IotCharging;
use zovye\CommissionBalance;
use zovye\Device;
use zovye\Group;
use zovye\Helper;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;
use zovye\Order;
use zovye\Pay;
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
    public static function chargingUserInfo() 
    {
        $user = common::getWXAppUser();
    
        $data = $user->profile();
        $data['banned'] = $user->isBanned();
        $data['commission_balance'] = $user->getCommissionBalance()->total();

        if (App::isChargingDeviceEnabled()) {
            $last_charging_data = $user->settings('chargingNOW', []);
            if ($last_charging_data) {
                $device = Device::get($last_charging_data['device']);
                if ($device) {
                    $serial = $last_charging_data['serial'];
                    $chargerID = $last_charging_data['chargerID'];
                    $chargerData = $device->getChargerBMSData($chargerID);
                    if ($chargerData && $chargerData['serial'] == $serial) {
                        $data['charging'] = [
                            'device' => $device->profile(),
                            'status' => $chargerData,
                        ];
                    }
                }
            }
        }

        return $data;
    }
    public static function groupList(): array
    {
        $query = Group::query(Group::CHARGING);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $keywords = request::trim('keywords');
        if ($keywords) {
            $query->where(['title REGEXP' => $keywords]);
        }

        $lng = request::float('lng');
        $lat = request::float('lat');

        if ($lng > 0 && $lat > 0) {
            $distanceFN = function ($loc) use ($lng, $lat) {
                $res = Util::getDistance($loc, ['lng' => $lng, 'lat' => $lat], 'driving');

                return is_error($res) ? 0 : $res;
            };
        } else {
            $distanceFN = function () {
                return 0;
            };
        }

        $order_by = sprintf("st_distance_sphere(POINT(%f,%f),loc) asc", $lng, $lat);
        $query->orderBy($order_by);

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

    public static function deviceList(): array
    {
        $id = request::int('id');
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            return err('找不到指定的分组信息！');
        }

        $query = Device::query(['group_id' => $group->getId()]);

        $keywords = request::trim('keywords');
        if ($keywords) {
            $query->whereOr([
                'name REGEXP' => $keywords,
                'IMEI REGEXP' => $keywords,
            ]);
        }

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $query->orderby('id desc');

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

    public static function deviceDetail(): array
    {
        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isChargingDevice()) {
            return err('这个设备不是充电桩！');
        }

        $result = $device->profile();
        $result['chargerNum'] = $device->getChargerNum();

        if (request::has('chargerID')) {
            $result['charger'] = $device->getChargerData(request::int('chargerID'));
        }

        $group = $device->getGroup();
        if ($group) {
            $result['group'] = $group->format();
        }

        return $result;
    }

    public static function start()
    {
        $user = common::getWXAppUser();

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = request::int('chargerID');
        $serial = $device->generateChargingSerial($chargerID);

        return IotCharging::start($serial, $user->getCommissionBalanceCard(), $device, $chargerID);
    }

    public static function stop()
    {
        $user = common::getWXAppUser();

        return IotCharging::stop($user);
    }

    public static function orderStatus(): array
    {
        $serial = request::str('serial');

        return IotCharging::orderStatus($serial);
    }

    public static function status(): array
    {
        $user = common::getWXAppUser();
        $last_charging_data = $user->settings('chargingNOW', []);

        if (isEmptyArray($last_charging_data)) {
            return err('没有发现正在充电的设备！');
        }

        $serial = $last_charging_data['serial'];

        return ['serial' => $serial];
    }

    public static function orderList(): array
    {
        $user = common::getWXAppUser();

        $query = Order::query([
            'openid' => $user->getOpenid(),
            'result_code' => 0,
            'src' => [Order::CHARGING, Order::CHARGING_UNPAID],
        ]);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $keywords = request::trim('keywords');
        if ($keywords) {
            $query->where(['order_id REGEXP' => $keywords]);
        }

        $query->orderby('id desc');

        $list = [];
        foreach ($query->findAll() as $order) {
            $list[] = Order::format($order, true);
        }

        return $list;
    }

    public static function orderDetail(): array
    {
        $user = common::getWXAppUser();

        $serial = request::str('serial');

        $order = Order::get($serial, true);
        if (empty($order)) {
            return err('找不到这个订单！');
        }

        $orderOwner = $order->getUser();
        if ($orderOwner && $orderOwner->getId() != $user->getId()) {
            return err('无法查看该订单！');
        }

        if (!$order->isChargingResultOk()) {
            $data = $order->getChargingResult();

            return err('订单没有完成，故障：'.$data['re']);
        }

        if (!$order->isChargingFinished()) {
            return err('订单没有完成！');
        }

        return Order::format($order, true);
    }

    public static function payForCharging(): array
    {
        $user = common::getWXAppUser();

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isChargingDevice()) {
            return err('不是充电桩设备！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        $chargerID = request::int('chargerID');

        $charging_data = $device->settings("chargingNOW.$chargerID");
        if (!empty($charging_data)) {
            return err('充电枪正在使用中！');
        }

        $price = intval(request::float('price', 0, 2) * 100);
        if ($price < 1) {
            return err('付款金额不正确！');
        }

        $serial = $device->generateChargingSerial($chargerID);

        return Helper::createChargingOrder($user, $device, $price, $serial);
    }

    public static function payForRecharge(): array
    {
        $user = common::getWXAppUser();

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $price = 1;//intval(request::float('price', 0, 2) * 100);
        if ($price < 1) {
            return err('付款金额不正确！');
        }

        return Helper::createRechargeOrder($user, $price);
    }

    public static function rechargeResult(): array
    {
        $order_no = request::str('orderNO');

        $pay_log = Pay::getPayLog($order_no);
        if (empty($pay_log)) {
            return err('找不到这个支付记录!');
        }

        if ($pay_log->isRecharged()) {
            return ['msg' => '充值已到账！', 'code' => 200];
        }

        if ($pay_log->isCancelled()) {
            return err('支付已取消');
        }

        if ($pay_log->isTimeout()) {
            return err('支付已超时！');
        }

        if ($pay_log->isRefund()) {
            return err('支付已退款！');
        }

        if ($pay_log->isPaid()) {
            return ['msg' => '支付已成功！'];
        }

        return ['msg' => '正在查询..'];
    }

    public static function rechargeList(): array
    {
        $user = common::getWXAppUser();

        $query = $user->getCommissionBalance()->log();
        $query->where(['src' => CommissionBalance::RECHARGE]);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);
        $query->page($page, $page_size);

        $query->orderBy('createtime DESC');

        $result = [];
        foreach($query->findAll() as $log) {
            $result[] = CommissionBalance::format($log);
        }

        return $result;
    }

    public static function withdraw(): array
    {
        $user = common::getWXAppUser();

        $total =  round(request::float('amount', 0, 2) * 100);

        return balance::balanceWithdraw($user, $total, request::str('memo'));
    }
}