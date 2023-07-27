<?php

namespace zovye\api\wxweb;

use zovye\api\wx\balance;
use zovye\api\wx\common;
use zovye\App;
use zovye\CacheUtil;
use zovye\Charging as IotCharging;
use zovye\ChargingNowData;
use zovye\Device;
use zovye\Group;
use zovye\Helper;
use zovye\LocationUtil;
use zovye\model\charging_now_dataModelObj;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;
use zovye\Order;
use zovye\Request;
use zovye\Team;
use zovye\User;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class charging
{
    public static function chargingUserInfo(): array
    {
        $user = common::getWXAppUser();

        $data = $user->profile();
        $data['banned'] = $user->isBanned();
        $data['commission_balance'] = $user->getCommissionBalance()->total();

        if (App::isTeamEnabled()) {
            $team = Team::getFor($user);
            if ($team) {
                $data['team'] = $team->profile();
            }
        }

        if (App::isChargingDeviceEnabled()) {
            $list = ChargingNowData::getAllByUser($user);

            if ($list) {
                $data['charging_now_data'] = [];

                /** @var charging_now_dataModelObj $charging_now_data */
                foreach ($list as $charging_now_data) {

                    $serial = $charging_now_data->getSerial();
                    $chargerID = $charging_now_data->getChargerId();

                    $device = $charging_now_data->getDevice();
                    $status = $device->getChargerBMSData($chargerID);

                    $order = Order::get($serial, true);

                    $data['charging_now_data'][] = [
                        'serial' => $serial,
                        'device' => $device->profile(),
                        'charger_id' => $chargerID,
                        'status' => $status,
                        'order' => $order->profile(),
                    ];

                    if ($status['serial'] == $serial) {
                        IotCharging::settleCharging($serial);
                    }
                }
            }
        }

        return $data;
    }

    public static function groupList(): array
    {
        $query = Group::query(Group::CHARGING);

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        $keywords = Request::trim('keywords');
        if ($keywords) {
            $query->where(['title REGEXP' => $keywords]);
        }

        $lng = Request::float('lng');
        $lat = Request::float('lat');

        if ($lng > 0 && $lat > 0) {
            $distanceFN = function ($loc) use ($lng, $lat) {
                $res = CacheUtil::cachedCall(30, function () use ($loc, $lng, $lat) {
                    return LocationUtil::getDistance($loc, ['lng' => $lng, 'lat' => $lat], 'driving');
                }, $loc, intval($lng * 1000), intval($lat * 1000));

                return is_error($res) ? 0 : $res;
            };
        } else {
            $distanceFN = function () {
                return 0;
            };
        }

        $query->orderBy(sprintf('st_distance_sphere(POINT(%f,%f),loc) asc', $lng, $lat));

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
        $id = Request::int('id');
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            return err('找不到指定的分组信息！');
        }

        $group_data = $group->format();

        $lng = Request::float('lng');
        $lat = Request::float('lat');

        if ($lng > 0 && $lat > 0) {
            $res = CacheUtil::cachedCall(10, function () use ($group_data, $lng, $lat) {
                return LocationUtil::getDistance($group_data['loc'], ['lng' => $lng, 'lat' => $lat], 'driving');
            }, $group_data['loc'], $lng, $lat);
            $distance = is_error($res) ? 0 : $res;
        } else {
            $distance = 0;
        }

        $group_data['distance'] = $distance;

        $group_data['devices'] = [
            'total' => Device::query(['group_id' => $group->getId()])->count(),
        ];

        return $group_data;
    }

    public static function deviceList(): array
    {
        $id = Request::int('id');
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            return err('找不到指定的分组信息！');
        }

        $query = Device::query(['group_id' => $group->getId()]);

        $keywords = Request::trim('keywords');
        if ($keywords) {
            $query->whereOr([
                'name REGEXP' => $keywords,
                'IMEI REGEXP' => $keywords,
            ]);
        }

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

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
                $data['charger'][] = $device->getChargerStatusData($i + 1);
            }

            $list[] = $data;
        }

        return $list;
    }

    public static function deviceDetail(): array
    {
        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isChargingDevice()) {
            return err('这个设备不是充电桩！');
        }

        $result = $device->profile();

        $result['chargerNum'] = $device->getChargerNum();

        if (Request::has('chargerID')) {
            $chargerID = Request::int('chargerID');
            $result['charger'] = $device->getChargerStatusData($chargerID);
            $result['charger']['index'] = $chargerID;
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

        if ($user->isBanned()) {
            return err('对不起，用户暂时无法使用！');
        }

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        IotCharging::checkUnfinishedOrder($device);

        $chargerID = Request::int('chargerID');

        $limit = Request::int('limit');
        $remark = Request::trim('remark');

        $serial = $device->generateChargingSerial($chargerID);

        return IotCharging::start($serial, $user->getCommissionBalanceCard(), $limit, $remark, $device, $chargerID);
    }

    public static function stop()
    {
        $user = common::getWXAppUser();

        $serial = Request::str('serial');

        if (!empty($serial)) {
            return IotCharging::stop($user, $serial);
        }

        $result = IotCharging::stopUserAllCharging($user);

        if (empty($result)) {
            return '已通知所有设备停止充电，请稍候！';
        }

        return $result;
    }

    public static function orderStatus(): array
    {
        $serial = Request::str('serial');

        return IotCharging::orderStatus($serial);
    }

    /** chargingStatus api */
    /**
     * 返回设备指定充电枪的状态
     * @return array
     */
    public static function status(): array
    {
        $user = common::getWXAppUser();

        $device_uid = Request::trim('deviceId');
        $charger_id = Request::int('chargerID');

        if (empty($device_uid) || empty($charger_id)) {
            return err('请求参数错误！');
        }

        $device = Device::get($device_uid, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $charging_now_data = ChargingNowData::getByDevice($device, $charger_id);
        if ($charging_now_data) {
            if ($charging_now_data->getUserId() != $user->getId()) {
                return err('设备正在使用中！');
            }

            return [
                'serial' => $charging_now_data->getSerial(),
                'createtime' => date('Y-m-d H:is', $charging_now_data->getCreatetime()),
            ];
        }

        return [];
    }

    public static function orderList(): array
    {
        $user = common::getWXAppUser();

        $query = Order::query([
            'openid' => $user->getOpenid(),
            'result_code' => 0,
            'src' => [Order::CHARGING, Order::CHARGING_UNPAID],
        ]);

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $keywords = Request::trim('keywords');
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

        $serial = Request::str('serial');

        $order = Order::get($serial, true);
        if (empty($order)) {
            return err('找不到这个订单！');
        }

        $orderOwner = $order->getUser();
        if ($orderOwner && $orderOwner->getId() != $user->getId()) {
            return err('无法查看该订单！');
        }

        if ($order->isChargingResultFailed()) {
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

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isChargingDevice()) {
            return err('不是充电桩设备！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        $chargerID = Request::int('chargerID');
        $remark = Request::trim('remark');

        $charging_now_data = ChargingNowData::getByDevice($device, $chargerID);
        if (!empty($charging_now_data)) {
            return err('充电枪正在使用中！');
        }

        $price = intval(round(Request::float('price', 0, 2) * 100));

        return Helper::createChargingOrder(
            $user,
            $device,
            $chargerID,
            $price,
            $device->generateChargingSerial($chargerID),
            $remark
        );
    }

    public static function withdraw(): array
    {
        $user = common::getWXAppUser();

        $total = intval(round(Request::float('amount', 0, 2) * 100));

        return balance::balanceWithdraw($user, $total, Request::str('memo'), [
            'charging' => true,
        ]);
    }
}