<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\Contract\ICard;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;

class Fueling
{
    public static function start(string $serial, ICard $card, deviceModelObj $device, $chargerID = 0)
    {
        return Util::transactionDo(function () use ($serial, $card, $device, $chargerID) {
            if (!$device->isFuelingDevice()) {
                return err('设备类型不正确！');
            }

            if (!$device->isMcbOnline(false)) {
                return err('设备离线，请稍后再试！');
            }

            if (!$device->lockAcquire()) {
                return err('锁定设备失败，请稍后再试！');
            }

            $goods = $device->getGoodsByLane(0);
            if (empty($goods)) {
                return err('没有指定商品信息！');
            }

            $user = $card->getOwner();

            $device_fueling_data = $device->settings("fuelingNOW.$chargerID", []);
            if ($device_fueling_data) {
                if ($device_fueling_data['user'] != $user->getId()) {
                    return err('设备正忙，请稍后再试！');
                }

                $order = Order::get($device_fueling_data['serial'], true);
                if ($order && !$order->isFuelingFinished()) {
                    return err('设备正在使用中！');
                }
            }

            if (!$user->acquireLocker(User::FUELING_LOCKER)) {
                return err('用户锁定失败，请稍后再试！');
            }

            if (self::hasUnpaidOrder($user)) {
                return err('请等待订单结算完成后再试！');
            }

            if (!$card->isUsable()) {
                return err('用户卡暂时不可用，请稍后再试！');
            }

            $total = $card->total();
            if ($total < 100) {
                return err('用户卡余额不足1.00元，请先充值后再试！');
            }

            if (empty($serial)) {
                $serial = time();
            }

            $order_data = [
                'src' => Order::FUELING_UNPAID,
                'order_id' => $serial,
                'openid' => $user->getOpenid(),
                'agent_id' => $device->getAgentId(),
                'device_id' => $device->getId(),
                'name' => $goods['name'],
                'goods_id' => $goods['id'],
                'num' => 1,
                'price' => 0,
                'ip' => Util::getClientIp(),
                'extra' => [
                    'device' => [
                        'imei' => $device->getImei(),
                        'name' => $device->getName(),
                    ],
                    'goods' => $goods,
                    'user' => $user->profile(),
                    'chargingID' => $chargerID,
                    'card' => $card->getUID(),
                    'cardType' => $card->getTypename(),
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

            if (!$device->updateSettings("fuelingNOW.$chargerID", [
                'serial' => $serial,
                'user' => $user->getId(),
                'time' => TIMESTAMP,
            ])) {
                return err('保存数据失败！');
            }

            if (!$user->updateSettings('fuelingNOW', [
                'serial' => $serial,
                'device' => $device->getId(),
                'chargerID' => $chargerID,
                'time' => TIMESTAMP,
            ])) {
                return err('保存数据失败！');
            }

            $result = $device->mcbNotify('run', '', [
                'ser' => $serial,
                'ch' => $chargerID,
                'timeout' => 60,
                'card' => $card->getUID(),
                'balance' => $card->total(),
            ]);

            if (!$result) {
                return err('设备通讯失败！');
            }

            //开启任务检查设备响应是否超时
            Job::fuelingStartTimeout($serial, $chargerID, $device->getId(), $user->getId(), $order->getId());

            return [
                'serial' => $serial,
                'msg' => '已通知设备开启，请开始加注！',
            ];
        });
    }

    public static function stop(userModelObj $user)
    {
        if (!$user->acquireLocker(User::FUELING_LOCKER)) {
            return err('用户锁定失败，请稍后再试！');
        }

        $fueling_data = $user->settings('fuelingNOW', []);

        if (isEmptyArray($fueling_data)) {
            return err('没有发现正在加注的设备！');
        }

        $device = Device::get($fueling_data['device']);
        if (empty($device)) {
            return err('设备不存在！');
        }

        if (!$device->lockAcquire()) {
            return err('设备正忙，请稍后再试！');
        }

        $chargerID = $fueling_data['chargerID'];

        $fueling_data = $device->settings("fuelingNOW.$chargerID", []);
        if ($fueling_data && $fueling_data['user'] != $user->getId()) {
            return err('其他用户正在使用当前设备！');
        }

        $serial = $fueling_data['serial'];

        if (!$device->mcbNotify('stop', '', [
            "ch" => $chargerID,
            "ser" => $fueling_data['serial'],
        ])) {
            return err('设备通信失败，请重试！');
        }

        Job::fuelingStopTimeout($serial);

        return '已通知设备停止，请稍候！';
    }

    public static function stopFueling(deviceModelObj $device, $chargerID, $serial)
    {
        if (!$device->mcbNotify('stop', '', [
            "ch" => $chargerID,
            "ser" => $serial,
        ])) {
            return err('设备通信失败，请重试！');
        }

        return true;
    }

    public static function end(string $serial, int $chargerID, callable $cb)
    {
        return Util::transactionDo(function () use ($serial, $chargerID, $cb) {

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

            if ($device->settings("fuelingNOW.$chargerID.serial", '') == $serial) {
                $device->removeSettings('fuelingNOW', $chargerID);
            }

            $user = $order->getUser();
            if (empty($user)) {
                return err('找不到对应的用户！');
            }

            if (!$user->acquireLocker(User::FUELING_LOCKER)) {
                return err('用户锁定失败，请稍后再试！');
            }

            if ($user->settings('fuelingNOW.serial', '') == $serial) {
                $user->remove('fuelingNOW');
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

    public static function orderStatus($serial): array
    {
        $order = Order::get($serial, true);
        if (empty($order)) {
            $pay_log = Pay::getPayLog($serial);
            if (empty($pay_log)) {
                return err('找不到这个订单记录！');
            } else {
                if ($pay_log->isCancelled()) {
                    return err('支付已取消！');
                }
                if ($pay_log->isTimeout()) {
                    return err('支付已超时！');
                }
                if ($pay_log->isRefund()) {
                    return err('支付已退款！');
                }
                if (!$pay_log->isPaid()) {
                    return ['message' => '正在查询支付结果..'];
                }

                return ['message' => '已支付，请稍等..'];
            }
        }

        $result = $order->getFuelingRecord();
        if ($result) {
            return ['record' => $result];
        }

        //todo 其它状态

    }

    public static function onEventOnline(deviceModelObj $device)
    {
        $config = $device->settings('fueling', [
            'solo' => 1,
        ]);

        $goods = $device->getGoodsByLane(0);
        if ($goods) {
            $config['price'] = $goods['price'];
        } else {
            $config['price'] = 0;
        }

        $result = $device->mcbNotify('config', '', $config);
        if (!$result) {
            Log::error('fueling', [
                'device' => $device->profile(),
                'error' => 'config device failed',
            ]);
        }
    }

    public static function onEventResult(deviceModelObj $device, $data)
    {
        $serial = strval($data['ser']);
        $chargerID = intval($data['ch']);

        if ($data['re'] != 3) {
            return self::end($serial, $chargerID, function (orderModelObj $order) use ($data) {
                $order->setChargingResult($data);
                $order->setResultCode($data['re']);
            });
        }

        /** @var orderModelObj $order */
        $order = Order::get($serial, true);
        if ($order) {
            $order->setChargingResult($data);
            return $order->save();
        }

        return false;
    }

    public static function onEventReport(deviceModelObj $device, $data)
    {
        $chargerID = intval($data['ch']);
        $device->setFuelingStatusData($chargerID, $data);
    }

    public static function onEventFee(deviceModelObj $device, $data)
    {
        $serial = strval($data['ser']);
        $chargerID = intval($data['ch']);
        self::end($serial, $chargerID, function (orderModelObj $order)  use ($data) {
            $order->setFuelingRecord($data);
        });
    }

    public static function hasUnpaidOrder(userModelObj $user): bool
    {
        return false;
    }
}