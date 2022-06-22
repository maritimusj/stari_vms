<?php

namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;

class Charging
{
    public static function start(userModelObj $user, deviceModelObj $device, $chargerID)
    {
        return Util::transactionDo(function () use ($user, $device, $chargerID) {
            if (!$device->isChargingDevice()) {
                return err('只支持充电桩设备！');
            }
    
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
                return err('锁定设备失败，请稍后再试！');
            }
    
            $device_charging_data = $device->settings("extra.charging.$chargerID", []);
            if ($device_charging_data) {
                if ($device_charging_data['user'] != $user->getId()) {
                    return err('设备正忙，请稍后再试！');
                }
                $order = Order::get($device_charging_data['serial'], true);
                if ($order && !$order->isChargingFinished()) {
                    return err('正在充电中！');
                }                
            }

            if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
                return err('用户锁定失败，请稍后再试！');
            }
    
            $user_charging_data = $user->settings('extra.charging', []);
            if ($user_charging_data) {
                if ($user_charging_data['device'] != $device->getId()) {
                    return err('用户卡正在使用中，请稍后再试！');
                }
                $order = Order::get($user_charging_data['serial'], true);
                if ($order && !$order->isChargingFinished()) {
                    return err('正在充电中！');
                }
            }
    
            $total = $user->getCommissionBalance()->total();
            if ($total < 1) {
                return err('用户卡余额不足，请先充值后再试！');
            }

            $serial = $device->generateChargingSerial($chargerID);
    
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
                    'chargingID' => $chargerID,
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

            $device->setChargerProperty($chargerID, [
                'timeTotal' => 0,
                'timeRemain' => 0,
                'chargedKWH' => 0,
                'priceTotal' => 0,
                'outputVoltage' => 0,
                'outputCurrent' => 0,
                'batteryMaxTemp' => 0,
                'chargerWireTemp' => 0,
                'soc' => 0,
                'error' => 0,
            ]);

            if (!$device->updateSettings("extra.charging.$chargerID", [
                'serial' => $serial,
                'user' => $user->getId(),
                'time' => TIMESTAMP,
            ])) {
                return err('保存数据失败！');
            }
            
            if (!$user->updateSettings('extra.charging', [
                'serial' => $serial,
                'device' => $device->getId(),
                'chargerID' => $chargerID,
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
    
            return [
                'serial' => $serial,
                'msg' => '已通知设备开启，请及时插入充电枪！',
            ];
        });
    }

    public static function stop(userModelObj $user)
    {
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

        if (!$device->mcbNotify('config', '', [
            "req" => "stop",
            "ch" => $chargerID,
            "ser" => $last_charging_data['serial'],
        ])) {
            return err('设备通信失败，请重试！');
        }

        return '已通知设备停止，请稍候！';
    }

    public static function orderStatus($serial)
    {
        $order = Order::get($serial, true);
        if (empty($order)) {
            return err('找不到这个订单！');
        }

        $result = $order->getChargingResult();
        if ($result && $result['re'] != 3) {
            return err("设备故障：{$result['re']}");
        }

        $result = $order->getChargingRecord();
        if ($result) {
            return ['record' => $result];
        }

        $result = ChargingServ::getChargingRecord($serial);
        if (is_error($result)) {
            return $result;
        }

        if (isset($result['totalPrice'])) {
            $chargerID = $order->getChargerID();
            self::end($serial, $chargerID, function($order) use($result) {
                $order->setChargingRecord($result);
                $order->setPrice($result['totalPrice'] * 100);                   
            });
            return ['record' => $result]; 
        }

        $device = $order->getDevice();
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = $order->getChargerID();
        $status = $device->getChargerData($chargerID);

        return ['status' => $status];
    }

    protected static function end(string $serial, int $chargerID, callable $cb)
    {
        return Util::transactionDo(function() use($serial, $chargerID, $cb) {
            $order = Order::get($serial, true);
            if (empty($order)) {
                return err('没有找到对应的订单！');
            }

            $device = $order->getDevice();
            if (empty($device)) {
                return err('找不到订单对应的设备！');
            }

            if (!$device->lockAcquire()) {
                return err('设备正忙，请稍后再试！');
            }

            $deviceChargingData = $device->settings("extra.charging.$chargerID", []);
            if ($deviceChargingData && $deviceChargingData['serial'] == $serial) {
                $device->updateSettings("extra.charging.$chargerID", []);
                if (!$device->save()) {
                    return err('保存数据失败！');
                }
            }

            $user = $order->getUser();
            if (empty($user)) {
                return err('找不到对应的用户！');
            }

            if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
                return err('用户锁定失败，请稍后再试！');
            }

            if ($user->settings('extra.charging.serial', '') == $serial) {
                $user->updateSettings('extra.charging', []);
            }

            if (!$user->save()) {
                return err('保存数据失败！');
            }

            if ($cb != null) {
                $cb($order);
            }

            if (!$order->save()) {
                return err('保存数据失败！');
            }

            return true;            
        });
    }

    public static function setResult(string $serial, int $chargerID, array $result)
    {
        if ($result['re'] != 3) {
            return self::end($serial, $chargerID, function (orderModelObj $order) use ($result) {
                $order->setChargingResult($result);
            });            
        }

        $order = Order::get($serial, true);
        if ($order) {
            $order->setChargingResult($result);
            return $order->save();
        }

        return false;
    }

    public static function settle(string $serial, int $chargerID, array $record)
    {
        return self::end($serial, $chargerID, function(orderModelObj $order) use ($record) {
            $order->setChargingRecord($record);
            $order->setPrice($record['totalPrice'] * 100);
        });
    }
}