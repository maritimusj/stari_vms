<?php

namespace zovye;

use zovye\Contract\ICard;
use zovye\model\charging_now_dataModelObj;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\pay_logsModelObj;
use zovye\model\userModelObj;

class Charging
{
    const FINISHED = 'finished';
    const STOPPED = 'stopped';
    const STATUS = 'status';

    public static function getMaxDevicesNum()
    {
        return Config::charging('device.max', 1);
    }

    public static function checkUnfinishedOrder(deviceModelObj $device)
    {
        $query = Order::query(['src' => Order::CHARGING_UNPAID, 'device_id' => $device->getId()]);
        /** @var orderModelObj $order */
        foreach ($query->findAll() as $order) {
            self::checkOrder($order);
        }
    }

    public static function getUnpaidOrderPriceTotal(userModelObj $user)
    {
        $total = 0;

        $query = Order::query(['src' => Order::CHARGING_UNPAID, 'openid' => $user->getOpenid()]);

        /** @var orderModelObj $order */
        foreach ($query->findAll() as $order) {
            if ($order->isChargingFinished()) {
                continue;
            }
            $last_charging_status = $order->getChargingStatus();
            if ($last_charging_status && $last_charging_status['priceTotal'] > 0) {
                $total += $last_charging_status['priceTotal'];
            }
        }

        return $total;
    }

    public static function start(
        string $serial,
        ICard $card,
        int $limit,
        deviceModelObj $device,
        $chargerID,
        $extra = []
    ) {
        if (!$device->isChargingDevice()) {
            return err('设备类型不正确！');
        }

        $data = $device->getChargerStatusData($chargerID);
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

        return Util::transactionDo(function () use ($card, $limit, $device, $chargerID, $serial, $group, $extra) {

            $user = $card->getOwner();

            $device_charging_data = ChargingNowData::getByDevice($device, $chargerID);
            if ($device_charging_data) {
                if ($device_charging_data->getUserId() != $user->getId()) {
                    return err('设备正忙，请稍后再试！');
                }

                $order = Order::get($device_charging_data->getSerial(), true);
                if ($order && !$order->isChargingFinished()) {
                    return err('设备正在充电中！');
                }
            }

            if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
                return err('用户锁定失败，请稍后再试！');
            }

            if (ChargingNowData::countByUser($user) >= self::getMaxDevicesNum()) {
                return err('正在充电设备已经超出最大限制，请稍后再试！');
            }

            $remaining = $card->total() - self::getUnpaidOrderPriceTotal($user);
            if ($remaining < 100) {
                return err('用户卡余额已不足，请先充值后再试！');
            }

            $balance = $limit > 0 ? min($limit, $remaining) : $remaining;

            $order_data = [
                'src' => Order::CHARGING_UNPAID,
                'order_id' => $serial,
                'openid' => $user->getOpenid(),
                'agent_id' => $device->getAgentId(),
                'device_id' => $device->getId(),
                'name' => $group->getName(),
                'goods_id' => $group->getId(),
                'num' => 1,
                'price' => 0,
                'account' => empty($acc) ? '' : $acc->name(),
                'ip' => $extra['ip'] ?? Util::getClientIp(),
                'extra' => [
                    'group' => $group->profile(),
                    'device' => [
                        'imei' => $device->getImei(),
                        'name' => $device->getName(),
                    ],
                    'user' => $user->profile(),
                    'chargerID' => $chargerID,
                    'card' => [
                        'uid' => $card->getUID(),
                        'balance' => $balance,
                        'type' => $card->getTypename(),
                    ],
                ],
            ];

            if ($extra['pay_name']) {
                $order_data['extra']['card']['pay_name'] = $extra['pay_name'];
            }

            $agent = $device->getAgent();
            if ($agent) {
                $order_data['extra']['agent'] = $agent->profile();
            }

            $order = Order::create($order_data);
            if (empty($order)) {
                return err('创建订单失败！');
            }

            if (!$device->setChargerProperty($chargerID, [
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
                'timestamp' => 0,
            ])) {
                return err('重置充电枪状态失败！');
            }

            if (!$device->setChargerBMSData($chargerID, [])) {
                return err('重置充电枪状态失败！');
            }

            if (!$device->save()) {
                return err('保存设备状态失败！');
            }

            if (!ChargingNowData::set($serial, $user, $device, $chargerID)) {
                return err('保存数据失败！');
            }

            if (!$device->mcbNotify('run', '', [
                'ser' => $serial,
                'ch' => $chargerID,
                'timeout' => 60,
                'card' => $card->getUID(),
                'balance' => $balance,
            ])) {
                return err('设备通信失败！');
            }

            //开启一个任务检查设备响应是否超时
            Job::chargingStartTimeout($serial, $chargerID, $device->getId(), $user->getId(), $order->getId());

            return [
                'serial' => $serial,
                'msg' => '已通知设备开启，请及时插入充电枪！',
            ];
        });
    }

    public static function stop(userModelObj $user, string $serial)
    {
        if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
            return err('用户锁定失败，请稍后再试！');
        }

        $user_charging_data = ChargingNowData::getByUser($user, $serial);

        if (empty($user_charging_data)) {
            return err('没有发现正在充电的设备！');
        }

        $device = $user_charging_data->getDevice();
        if (empty($device)) {
            return err('设备不存在！');
        }

        if (!$device->lockAcquire()) {
            return err('设备正忙，请稍后再试！');
        }

        $chargerID = $user_charging_data->getChargerId();

        $last_charging_data = ChargingNowData::getByDevice($device, $chargerID);
        if ($last_charging_data && $last_charging_data->getUserId() != $user->getId()) {
            return err('其他用户正在使用当前设备！');
        }

        $serial = $last_charging_data->getSerial();

        if (!$device->mcbNotify('config', '', [
            'req' => 'stop',
            'ch' => $chargerID,
            'ser' => $serial,
        ])) {
            return err('设备通信失败，请重试！');
        }

        Job::chargingStopTimeout($serial);

        return '已通知设备停止充电，请稍候！';
    }

    public static function stopCharging(deviceModelObj $device, $chargerID, $serial)
    {
        if (!$device->mcbNotify('config', '', [
            'req' => 'stop',
            'ch' => $chargerID,
            'ser' => $serial,
        ])) {
            return err('设备通信失败，请重试！');
        }

        return true;
    }

    public static function stopUserAllCharging(userModelObj $user): array
    {
        $err = [];
        /** @var charging_now_dataModelObj $charging_now_data */
        foreach (ChargingNowData::getAllByUser($user) as $charging_now_data) {
            $serial = $charging_now_data->getSerial();
            $device = $charging_now_data->getDevice();
            $chargerID = $charging_now_data->getChargerId();
            if ($serial && $device) {
                $result = self::stopCharging($device, $chargerID, $serial);
                if (is_error($result)) {
                    $err[] = [
                        'device' => $device->profile(),
                        'chargerID' => $chargerID,
                        'error' => $result,
                    ];
                }
            }
        }

        return $err;
    }

    public static function orderStatus($serial): array
    {
        $order = Order::get($serial, true);
        if (empty($order)) {
            $pay_log = Pay::getPayLog($serial);
            if (empty($pay_log)) {
                return err('找不到这个订单记录！');
            }

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

        $result = $order->getChargingRecord();
        if ($result) {
            return ['record' => $result];
        }

        $finished = $order->getChargingBMSData(self::FINISHED);
        if ($finished) {
            $result = ChargingServ::getChargingRecord($serial);
            if (!is_error($result) && isset($result['totalPrice'])) {
                $chargerID = $order->getChargerID();
                self::settle($serial, $chargerID, $result);

                return ['record' => $result];
            }

            return ['finished' => $finished];
        }

        $stopped = $order->getChargingBMSData(self::STOPPED);
        if ($stopped) {
            return ['stopped' => $stopped];
        }

        $timeout = $order->getExtraData('timeout', []);
        if ($timeout) {
            return err($timeout['reason'] ?? '设备响应超时！');
        }

        $bms = $order->getChargingBMSData(self::STATUS);
        if ($bms && time() - $bms['timestamp'] > 120) {
            $chargerID = $order->getChargerID();
            self::end($serial, $chargerID, function ($order) {
                $order->setExtraData('timeout', [
                    'at' => time(),
                    'reason' => '充电枪上报数据超时！',
                ]);
            });

            return err('充电枪上报数据超时！');
        }

        $result = $order->getChargingResult();
        if ($result && $result['re'] != 3) {
            if ($result['re'] == 112) {
                return err('启动失败：正在充电中');
            } elseif ($result['re'] == 113) {
                return err('启动失败：设备故障');
            } elseif ($result['re'] == 114) {
                return err('启动失败：设备离线');
            } elseif ($result['re'] == 115) {
                return err('启动失败：充电枪没有插入');
            } elseif ($result['re'] == 121) {
                return err('启动失败：设备通讯失败');
            } elseif ($result['re'] == 122) {
                return err('启动失败：设备响应超时');
            }

            return err('启动失败：设备故障'.($result['re'] - 110));
        }

        $device = $order->getDevice();
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $chargerID = $order->getChargerID();
        $status = $device->getChargerStatusData($chargerID);

        $group = $device->getGroup();
        if ($group) {
            $status['serviceFee'] = round($group->getServiceFee() * floatval($status['chargedKWH']), 2);
            $status['priceTotal'] = round($status['priceTotal'] - $status['serviceFee'], 2);
        }

        return ['status' => $status];
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

            $charging_now_data = ChargingNowData::getByDevice($device, $chargerID);
            if (empty($charging_now_data) || $charging_now_data->getSerial() != $serial) {
                return err('找不到这个设备的充电信息！');
            }

            $user = $charging_now_data->getUser();
            if (empty($user)) {
                return err('找不到对应的用户！');
            }

            if (!$user->acquireLocker(User::CHARGING_LOCKER)) {
                return err('用户锁定失败，请稍后再试！');
            }

            if ($cb != null) {
                $cb($order);
            }

            if (!$order->save()) {
                return err('保存数据失败！');
            }
            
            return $charging_now_data->destroy();
        });
    }

    public static function setResult(string $serial, int $chargerID, array $result)
    {
        if ($result['re'] != 3) {
            return self::end($serial, $chargerID, function (orderModelObj $order) use ($result, $serial) {
                $order->setChargingResult($result);
                $order->setResultCode($result['re']);

                //如果是即时支付，则尝试退款
                $pay_log = Pay::getPayLog($serial);
                if ($pay_log) {
                    Job::refund($serial, '充电订单失败退款');
                }
            });
        }

        /** @var orderModelObj $order */
        $order = Order::get($serial, true);
        if ($order) {
            $order->setChargingResult($result);

            return $order->save();
        }

        return false;
    }

    public static function settle(string $serial, int $chargerID, array $record)
    {
        if (!Locker::try($serial)) {
            return err('锁定订单失败！');
        }

        return self::end($serial, $chargerID, function (orderModelObj $order) use ($serial, $chargerID, $record) {
            $order->setChargingRecord($record);

            if ($order->getSrc() == Order::CHARGING) {
                return true;
            }

            $totalPrice = intval(round($record['totalPrice'] * 100));

            $order->setPrice($totalPrice);
            $order->setExtraData('timeout', []);
            $order->setSrc(Order::CHARGING);

            $device = $order->getDevice();
            $user = $order->getUser();

            $pay_log = Pay::getPayLog($serial);
            if ($pay_log) {
                $remain = $pay_log->getPrice() - $totalPrice;
                if ($remain > 0) {
                    Job::refund($serial, '充电订单结算退款');
                }
                //事件：订单已经创建
                EventBus::on('device.orderCreated', [
                    'device' => $device,
                    'user' => $user,
                    'order' => $order,
                ]);
            } else {
                //扣除用户账户金额
                if ($totalPrice > 0) {
                    $balance = $user->getCommissionBalance();
                    $extra = [
                        'orderid' => $order->getId(),
                        'serial' => $serial,
                        'chargerID' => $chargerID,
                    ];
                    if ($balance->change(0 - $totalPrice, CommissionBalance::CHARGING_FEE, $extra)) {
                        //事件：订单已经创建
                        EventBus::on('device.orderCreated', [
                            'device' => $device,
                            'user' => $user,
                            'order' => $order,
                        ]);
                    } else {
                        Log::error('charging', [
                            'error' => '用户扣款失败！',
                            'data' => $extra,
                        ]);
                    }
                }
            }

            return true;
        });
    }

    public static function checkOrder(orderModelObj $order)
    {
        $serial = $order->getOrderNO();
        $res = ChargingServ::getChargingRecord($serial);
        if ($res && !is_error($res) && isset($res['totalPrice'])) {
            Charging::settle($serial, $res['chargerID'], $res);
        }
    }

    public static function settleCharging($serial)
    {
        $order = Order::get($serial, true);
        if ($order && !$order->isChargingFinished()) {
            self::checkOrder($order);
        }
    }

    public static function checkCharging(deviceModelObj $device, $chargerID)
    {
        $charging_now_data = ChargingNowData::getByDevice($device, $chargerID);
        if (empty($charging_now_data)) {

            return;
        }

        $serial = $charging_now_data->getSerial();
        $order = Order::get($serial, true);
        if (empty($order)) {
            $charging_now_data->destroy();

            return;
        }

        $res = ChargingServ::getChargingRecord($serial);
        if ($res && !is_error($res) && isset($res['totalPrice'])) {
            Charging::settle($serial, $chargerID, $res);

            return;
        }

        $chargerData = $device->getChargerStatusData($chargerID);
        if ($chargerData && $chargerData['status'] == 2) {
            Charging::end($serial, $chargerID, function ($order) {
                if (!$order->getChargingRecord()) {
                    $order->setExtraData('timeout', [
                        'at' => time(),
                        'reason' => '充电枪已停止充电！',
                    ]);
                }
            });
        }
    }

    public static function startFromPayLog(pay_logsModelObj $pay_log)
    {
        if (!$pay_log->isPaid()) {
            return err('未支付完成！');
        }

        if ($pay_log->isCancelled() || $pay_log->isTimeout() || $pay_log->isRefund() || $pay_log->isRecharged()) {
            return err('支付已无效!');
        }

        if ($pay_log->isCharging()) {
            return true;
        }

        $device_id = $pay_log->getDeviceId();

        $device = Device::get($device_id);
        if (empty($device)) {
            return err("找不到指定设备!");
        }

        if (!$device->isChargingDevice()) {
            return err("不是充电桩设备!");
        }

        $user = $pay_log->getOwner();
        if (empty($user)) {
            return err('找不到指定的用户!');
        }

        $chargerID = $pay_log->getChargerID();

        $res = self::start($pay_log->getOrderNO(), $pay_log, 0, $device, $chargerID, [
            'ip' => $pay_log->getData('ip', ''),
            'pay_name' => $pay_log->getPayName(),
        ]);
        if (is_error($res)) {
            return $res;
        }

        $pay_log->setData('charging', $res);
        if (!$pay_log->save()) {
            return err('保存数据失败！');
        }

        return true;
    }

    public static function onEventResult(deviceModelObj $device, $extra)
    {
        $res = self::setResult($extra['ser'], $extra['ch'], $extra);
        if (is_error($res)) {
            Log::error('charging', [
                'device' => $device->profile(),
                'data' => $extra,
                'error' => $res,
            ]);
        }
    }

    public static function onEventReport(deviceModelObj $device, $extra)
    {
        if (isset($extra['firmwareVersion']) && isset($extra['protocolVersion'])) {
            $device->setChargingData($extra);
        }

        if (is_array($extra['status'])) {
            $serial = $extra['serial'] ?? '';
            $chargerID = $extra['chargerID'];

            $extra['status']['timestamp'] = time();
            $device->setChargerData($chargerID, $extra['status']);

            if ($serial) {
                $order = Order::get($serial, true);
                if ($order) {
                    $totalPrice = round($extra['status']['priceTotal'] * 100);

                    $order->setPrice($totalPrice);
                    $order->setChargingStatus($extra['status']);
                    $order->save();

                    //检查充电金额是否已经多于付款金额或帐户余额
                    $pay_log = Pay::getPayLog($serial);
                    if ($pay_log) {
                        if ($totalPrice > $pay_log->total()) {
                            Charging::stopCharging($device, $chargerID, $serial);
                        }
                    } else {
                        //检查用户余额
                        $user = $order->getUser();
                        if (empty($user)) {
                            Charging::stopCharging($device, $chargerID, $serial);
                        } else {
                            if (self::getUnpaidOrderPriceTotal($user) > $user->getCommissionBalanceCard()->total()) {
                                Charging::stopUserAllCharging($user);
                            }
                        }
                    }
                }
            } else {
                if ($extra['status']['status'] == 2) {
                    //空闲
                    Charging::checkCharging($device, $chargerID);
                }
            }
        }

        if (is_array($extra['BMS'])) {
            $serial = $extra['serial'] ?? '';
            if ($serial) {
                $chargerID = $extra['chargerID'];

                $extra['BMS']['serial'] = $serial;
                $extra['BMS']['chargerID'] = $chargerID;

                $device->setChargerBMSData($chargerID, $extra['BMS']);

                $extra['BMS']['timestamp'] = time();

                if ($extra['BMS']['event'] == self::FINISHED) {
                    Charging::end($serial, $chargerID, function (orderModelObj $order) use ($extra) {
                        $order->setChargingBMSData(self::FINISHED, $extra['BMS']['data']);
                    });
                } elseif ($extra['BMS']['event'] == self::STOPPED) {
                    Charging::end($serial, $chargerID, function (orderModelObj $order) use ($extra) {
                        $order->setChargingBMSData(self::STOPPED, $extra['BMS']['data']);
                    });
                } else {
                    $order = Order::get($serial, true);
                    if ($order) {
                        $order->setChargingBMSData(self::STATUS, $extra['BMS']['data']);
                        $order->save();
                    }
                }
            }
        }

        if (is_array($extra['record'])) {
            $serial = $extra['serial'] ?? '';
            $chargerID = intval($extra['chargerID']);
            if ($serial && $chargerID) {
                $res = Charging::settle($serial, $chargerID, $extra['record']);
                if (is_error($res)) {
                    Log::error('charging', [
                        'serial' => $serial,
                        'chargerID' => $chargerID,
                        'data' => $extra['record'],
                        'error' => $res,
                    ]);
                } else {
                    Log::info('charging', [
                        'data' => $extra,
                        'res' => $res,
                    ]);
                }
            }
        }
    }
}