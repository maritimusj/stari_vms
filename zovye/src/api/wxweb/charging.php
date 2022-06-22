<?php

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\Charging as IotCharging;
use zovye\ChargingServ;
use zovye\Device;
use zovye\Group;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;
use zovye\Order;
use zovye\request;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class charging
{
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
            $distanceFN = function($loc) use($lng, $lat) {
                $res = Util::getDistance($loc, ['lng' => $lng, 'lat' => $lat], 'driving');
                return is_error($res) ? 0 : $res;
            };     
        } else {
            $distanceFN = function() { return 0;};
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

    public static function deviceDetail()
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
        $user = common::getUser();

        $device = Device::get(request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = request::int('chargerID');

        return IotCharging::start($user, $device, $chargerID);
    }

    public static function stop()
    {
        $user = common::getUser();

        if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
            return err('用户锁定失败，请稍后再试！');
        }
        
        return IotCharging::stop($user);
    }

    public static function orderStatus()
    {
        $serial = request::str('serial');

        return IotCharging::orderStatus($serial);
    }

    public static function status()
    {
        $user = common::getUser();
        $last_charging_data = $user->settings('extra.charging', []);

        if (isEmptyArray($last_charging_data)) {
            return err('没有发现正在充电的设备！');
        }

        $serial = $last_charging_data['serial'];
        return ['serial' => $serial];
    }

}