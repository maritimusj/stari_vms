<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\Contract\ICard;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\pay_logsModelObj;
use zovye\model\userModelObj;

class Fueling
{
    public static function start(string $serial, ICard $card, deviceModelObj $device, $chargerID = 0)
    {
        return Util::transactionDo(function () use ($serial, $card, $device, $chargerID) {
            if (!$device->isFuelingDevice()) {
                return err('设备类型不正确！');
            }

            if ($device->isExpired()) {
                return err('设备授权已过期，请联系管理员!');
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
                $serial = Order::makeSerial($user);
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
            'ch' => $chargerID,
            'ser' => $fueling_data['serial'],
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

    public static function startFromPayLog(pay_logsModelObj $pay_log)
    {
        if (!$pay_log->isPaid()) {
            return err('未支付完成！');
        }

        if ($pay_log->isCancelled() || $pay_log->isTimeout() || $pay_log->isRefund() || $pay_log->isRecharged()) {
            return err('支付已无效!');
        }

        if ($pay_log->isFueling()) {
            return true;
        }

        $device_id = $pay_log->getDeviceId();

        $device = Device::get($device_id);
        if (empty($device)) {
            return err("找不到指定设备!");
        }

        if (!$device->isFuelingDevice()) {
            return err("设备类型不正确!");
        }

        $user = $pay_log->getOwner();
        if (empty($user)) {
            return err('找不到指定的用户!');
        }

        $chargerID = $pay_log->getChargerID();

        $res = self::start($pay_log->getOrderNO(), $pay_log, $device, $chargerID);
        if (is_error($res)) {
            return $res;
        }

        $pay_log->setData('fueling', $res);
        if (!$pay_log->save()) {
            return err('保存数据失败！');
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

            $res = $device->mcbNotify('confirm', '', [
                'ser' => $serial,
            ]);

            if (!$res) {
                Log::error('fueling', [
                    'err' => '计费确认失败：设备通讯失败！'
                ]);
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

        $timeout = $order->getExtraData('timeout', []);
        if ($timeout) {
            return err($timeout['reason'] ?? '设备响应超时！');
        }

        $result = $order->getFuelingResult();
        if ($result && $result['re'] != 3) {
            return err("启动失败：设备故障".($result['re']));
        }

        $device = $order->getDevice();
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = $order->getChargerID();
        $status = $device->getFuelingStatusData($chargerID);
        if ($status['ser'] === $order->getOrderNO()) {
            return ['status' => $status];
        }

        return ['message' => '正在查询状态！'];
    }

    public static function config(deviceModelObj $device)
    {
        $config = $device->getFuelingConfig();
        $result = $device->mcbNotify('config', '', $config);
        if (!$result) {
            return err('通知设备更新配置失败！');
        }

        return true;
    }

    public static function onEventOnline(deviceModelObj $device)
    {
        $res = self::config($device);
        if (is_error($res)) {
            Log::error('fueling', [
                'config' => $device->getFuelingConfig(),
                'device' => $device->profile(),
                'error' => $res,
            ]);
        }
    }

    public static function onEventResult(deviceModelObj $device, $data)
    {
        $serial = strval($data['ser']);
        $chargerID = intval($data['ch']);

        if ($data['re'] != 3) {
            return self::end($serial, $chargerID, function (orderModelObj $order) use ($data) {
                $order->setFuelingResult($data);
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
        $serial = strval($data['ser']);
        if ($serial) {
            $chargerID = intval($data['ch']);
            $device->setFuelingStatusData($chargerID, $data);

            //检查当前费用是否已经超出支付费用或卡余额
            $total_price = intval($data['price_total']);
            if ($total_price) {
                $pay_log = Pay::getPayLog($serial, LOG_FUELING_PAY);
                if ($pay_log) {
                    if ($total_price > $pay_log->getTotal()) {
                        self::stopFueling($device, $chargerID, $serial);
                    }
                } else {
                    $order = Order::get($serial, true);
                    if ($order) {
                        self::stopFueling($device, $chargerID, $serial);
                    }
                }
            }
        }
    }

    public static function onEventFee(deviceModelObj $device, $data)
    {
        $serial = strval($data['ser']);
        $chargerID = intval($data['ch']);
        self::end($serial, $chargerID, function (orderModelObj $order) use ($data) {
            $order->setFuelingRecord($data);
        });
    }

    public static function hasUnpaidOrder(userModelObj $user): bool
    {
        return false;
    }
}