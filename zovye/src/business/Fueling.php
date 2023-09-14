<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\business;

use Exception;
use zovye\contract\ICard;
use zovye\domain\CommissionBalance;
use zovye\domain\Device;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\EventBus;
use zovye\Job;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\pay_logsModelObj;
use zovye\model\userModelObj;
use zovye\model\vipModelObj;
use zovye\Pay;
use zovye\util\DBUtil;
use zovye\util\Util;
use zovye\We7;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class Fueling
{
    public const MODE_SOLO = 0; //单机模式
    public const MODE_REMOTE = 1; //联网模式

    public static function test(deviceModelObj $device, int $amount, $chargerID = Device::DEFAULT_CARGO_LANE): bool
    {
        return $device->mcbNotify('run', '', [
            'ser' => $serial ?? Util::random(16, true),
            'ch' => $chargerID,
            'amount' => $amount,
        ]);
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

    public static function confirm(deviceModelObj $device, string $serial)
    {
        $res = $device->mcbNotify('confirm', '', [
            'ser' => $serial,
        ]);

        if (!$res) {
            return err('计费确认失败：设备通讯失败！');
        }

        return true;
    }

    public static function start(string $serial, ICard $card, deviceModelObj $device, $chargerID = 0, $extra = [])
    {
        return DBUtil::transactionDo(function () use ($serial, $card, $device, $chargerID, $extra) {
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

            $goods = $device->getGoodsByLane($chargerID, [], false);
            if (empty($goods)) {
                return err('没有指定商品信息！');
            }

            $user = $card->getOwner();

            $device_fueling_data = $device->fuelingNOWData($chargerID);
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
            if ($total < 1) {
                return err('用户卡余额不足，请先充值后再试！');
            }

            if (empty($serial)) {
                $serial = Order::makeSerial($user);
            }

            $order_data = [
                'src' => Order::FUELING_UNPAID,
                'order_id' => $serial,
                'transaction_id' => $extra['transaction_id'] ?? '',
                'openid' => $user->getOpenid(),
                'user_id' => $user->getId(),
                'agent_id' => $device->getAgentId(),
                'device_id' => $device->getId(),
                'name' => $goods['name'],
                'goods_id' => $goods['id'],
                'num' => 1,
                'price' => 0,
                'ip' => $extra['ip'] ?? CLIENT_IP,
                'extra' => [
                    'device' => [
                        'imei' => $device->getImei(),
                        'name' => $device->getName(),
                    ],
                    'goods' => $goods,
                    'user' => $user->profile(),
                    'chargerID' => $chargerID,
                    'card' => [
                        'uid' => $card->getUID(),
                        'balance' => $card->total(),
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

            if (!$device->setFuelingNOWData($chargerID, [
                'serial' => $serial,
                'user' => $user->getId(),
                'time' => TIMESTAMP,
            ])) {
                return err('保存数据失败！');
            }

            if (!$user->setFuelingNOWData([
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

        $fueling_data = $user->fuelingNOWData();

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

        $chargerID = intval($fueling_data['chargerID']);

        $fueling_data = $device->fuelingNOWData($chargerID);
        if ($fueling_data && $fueling_data['user'] != $user->getId()) {
            return err('其他用户正在使用当前设备！');
        }

        $serial = strval($fueling_data['serial']);

        $result = self::stopFueling($device, $chargerID, $serial);
        if (is_error($result)) {
            return $result;
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

        $res = self::start($pay_log->getOrderNO(), $pay_log, $device, $chargerID, [
            'ip' => $pay_log->getData('ip', ''),
            'pay_name' => $pay_log->getPayName(),
            'transaction_id' => $pay_log->getTransactionId(),
        ]);

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
        return DBUtil::transactionDo(function () use ($serial, $chargerID, $cb) {

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

            if ($device->fuelingNOWData($chargerID, 'serial', '') == $serial) {
                $device->removeFuelingNOWData($chargerID);
            }

            $user = $order->getUser();
            if (empty($user)) {
                return err('找不到对应的用户！');
            }

            if (!$user->acquireLocker(User::FUELING_LOCKER)) {
                return err('用户锁定失败，请稍后再试！');
            }

            if ($user->fuelingNOWData('serial', '') == $serial) {
                $user->removeFuelingNOWData();
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
            $pay_log = Pay::getPayLog($serial, LOG_FUELING_PAY);
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

        $ids = self::settleTimeoutOrder($device);
        if (in_array($order->getId(), $ids)) {
            $result = $order->getFuelingRecord();
            if ($result) {
                return ['record' => $result];
            }
        }

        return ['message' => '正在查询状态...'];
    }

    /**
     * 结算订单
     * @param deviceModelObj $device
     * @param array $data
     * @return array|mixed
     */
    public static function settle(deviceModelObj $device, array $data)
    {
        $serial = strval($data['ser']);
        $chargerID = intval($data['ch']);

        if (!Locker::try("settle:$serial", REQUEST_ID, 3)) {
            Log::error("fueling", [
                'error' => '结算订单时，锁定失败！',
                'data' => $data,
                'device' => $device->profile(),
            ]);

            return err('订单锁订失败！');
        }

        self::end($serial, $chargerID, function (orderModelObj $order) use ($device, $serial, $data) {
            if (isset($data['price_total'])) {
                $total_price = intval($data['price_total']);
                $order->setPrice($total_price);
            }

            if (isset($data['amount'])) {
                $amount = intval($data['amount']);
                $order->setNum($amount);
            }

            $order->setFuelingRecord($data);
        });

        $result = DBUtil::transactionDo(function () use ($serial, $device, $chargerID) {
            $order = Order::findOne(['order_id' => $serial, 'src' => Order::FUELING_UNPAID]);
            if ($order) {
                $order->setSrc(Order::FUELING);

                $num = intval($order->getNum() * 100); // 需要x100
                if ($num > 0) {
                    //减少库存
                    $locker = $device->payloadLockAcquire(3);
                    if (empty($locker)) {
                        return err('设备正忙，请重试！');
                    }

                    $res = $device->resetPayload([$chargerID => -$num], "订单：$serial");
                    if (is_error($res)) {
                        return err('保存库存变动失败！');
                    }

                    $locker->unlock();
                }

                $user = $order->getUser();
                $card_type = $order->getExtraData('card.type', '');

                if ($card_type != VIPCard::getTypename()) {
                    try {
                        //事件：订单已经创建
                        EventBus::on('device.orderCreated', [
                            'device' => $device,
                            'user' => $user,
                            'order' => $order,
                        ]);
                    } catch (Exception $e) {
                        Log::error('fueling', [
                            'event' => '处理订单创建事发生错误！',
                            'error' => $e->getMessage(),
                            'data' => [
                                'order' => $order->profile(),
                                'user' => $user->profile(),
                            ],
                        ]);
                    }
                }

                $pay_log = Pay::getPayLog($serial);
                if ($pay_log) {
                    $remain = $pay_log->getPrice() - $order->getPrice();
                    if ($remain > 0) {
                        Job::refund($serial, '订单结算退款');
                    }
                } else {
                    //扣除用户账户金额
                    if ($card_type == UserCommissionBalanceCard::getTypename()) {
                        if ($order->getPrice() > 0) {
                            $balance = $user->getCommissionBalance();
                            $extra = [
                                'orderid' => $order->getId(),
                                'serial' => $serial,
                                'chargerID' => $chargerID,
                            ];
                            if (!$balance->change(0 - $order->getPrice(), CommissionBalance::FUELING_FEE, $extra)) {
                                return err('用户扣款失败！');
                            }
                        }
                    }
                }

                if (!$order->save()) {
                    return err('保存订单失败！');
                }

                Job::orderNotify($order);
            }

            return true;
        });

        if (is_error($result)) {
            Log::error('fueling', [
                'error' => '结算订单出错！',
                'result' => $result,
                'data' => $data,
            ]);
        }

        return $result;
    }

    // 处理超时订单
    public static function settleTimeoutOrder(
        deviceModelObj $device,
        string $exclude_serial = '',
        $timeout = 300
    ): array {
        $query = Order::query(['src' => Order::FUELING_UNPAID, 'device_id' => $device->getId()]);

        $result = [];

        /** @var orderModelObj $order */
        foreach ($query->findAll() as $order) {
            if ($exclude_serial && $order->getOrderNO() == $exclude_serial) {
                continue;
            }
            $last_update_time = $order->getExtraData('fueling.stats.time', $order->getCreatetime());
            if ($exclude_serial || time() - $last_update_time > $timeout) {
                self::settle($device, [
                    'ser' => $order->getOrderNO(),
                    'ch' => $order->getChargerID(),
                    'reason' => -1,
                    'time' => time(),
                ]);
                $result[] = $order->getId();
            }
        }

        if ($result) {
            Log::error('fueling', [
                'op' => 'settleTimeoutOrder',
                'result' => $result,
            ]);
        }

        return $result;
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
                $order->setSrc(Order::FUELING);
                $order->setFuelingResult($data);
                $order->setResultCode($data['re']);

                $pay_log = Pay::getPayLog($order->getOrderNO());
                if ($pay_log) {
                    Job::refund($order->getOrderNO(), '订单失败退款');
                }
            });
        }

        /** @var orderModelObj $order */
        $order = Order::get($serial, true);
        if ($order) {
            $order->setFuelingResult($data);

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
            $should_stop_fueling = false;
            $total_price = intval($data['price_total']);

            $order = Order::get($serial, true);
            if ($order) {
                if ($order->getSrc() != Order::FUELING_UNPAID) {
                    $should_stop_fueling = true;
                }

                if ($data['amount']) {
                    $amount = intval($data['amount']);
                    $order->setNum($amount);
                }

                //更新订单状态
                if ($total_price) {
                    $order->setPrice($total_price);

                    //检查余额是否有剩余
                    $pay_log = Pay::getPayLog($serial, LOG_FUELING_PAY);
                    if ($pay_log) {
                        if ($total_price >= $pay_log->getPrice()) {
                            $should_stop_fueling = true;
                        }
                    } else {
                        $user = $order->getUser();
                        if (empty($user) || $user->isBanned()) {
                            $should_stop_fueling = true;
                        } else {
                            $card = self::isVIP($user, $device) ? $user->getVIPCard() : $user->getCommissionBalanceCard(
                            );
                            if ($total_price >= $card->total()) {
                                $should_stop_fueling = true;
                            }
                        }
                    }
                }

                $order->setExtraData('fueling.status.time', time());
                $order->save();
            }

            if ($should_stop_fueling) {
                self::stopFueling($device, $chargerID, $serial);
            }

            self::settleTimeoutOrder($device, $serial);
        }
    }

    public static function isVIP(userModelObj $user, deviceModelObj $device): bool
    {
        $agent = $device->getAgent();
        if ($agent) {
            $vip = VIP::getFor($agent, $user);
            if (empty($vip)) {
                $mobile = $user->getMobile();
                if ($mobile) {
                    /** @var vipModelObj $vip */
                    $vip = VIP::getByMobile($agent, $mobile);
                    if ($vip && !empty($vip->getUserId())) {
                        return false;
                    }
                }
            }

            return $vip && $vip->hasPrivilege($device);
        }

        return false;
    }

    public static function getSoloUser(): userModelObj
    {
        return User::getOrCreate('fueling_user_'.We7::uniacid(), User::PSEUDO, [
            'nickname' => '普通用户',
            'avatar' => MODULE_URL.'static/img/unknown.svg',
        ]);
    }

    public static function createOrderFromSoloModeData(deviceModelObj $device, $data)
    {
        $serial = strval($data['ser']);
        if (empty($serial)) {
            return err('不正确的计费数据！');
        }

        $chargerID = intval($data['ch']);

        $user = self::getSoloUser();

        $order_no = Order::makeSerial($device, $serial);
        if (Order::exists($order_no)) {
            return true;
        }

        $goods = $device->getGoodsByLane($chargerID, [], false);

        $order_data = [
            'src' => Order::FUELING_SOLO,
            'order_id' => $order_no,
            'openid' => $user->getOpenid(),
            'user_id' => $user->getId(),
            'agent_id' => $device->getAgentId(),
            'device_id' => $device->getId(),
            'name' => $goods['name'] ?? '尿素商品',
            'goods_id' => $goods['id'] ?? 0,
            'num' => intval($data['amount']),
            'price' => intval($data['price_total']),
            'ip' => "",
            'extra' => [
                'device' => [
                    'imei' => $device->getImei(),
                    'name' => $device->getName(),
                ],
                'goods' => $goods,
                'user' => $user->profile(),
                'chargerID' => $chargerID,
                'fueling' => [
                    'result' => [
                        're' => 3,
                    ],
                    'record' => $data,
                ],
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

        Job::orderNotify($order);

        return $order;
    }

    public static function onEventFee(deviceModelObj $device, $data)
    {
        if ($data['solo'] === self::MODE_SOLO) {
            $result = self::createOrderFromSoloModeData($device, $data);
        } else {
            $result = self::settle($device, $data);
        }

        if (is_error($result)) {
            Log::error('fueling', [
                'error' => $result,
                'data' => $data,
            ]);

        } else {
            $serial = strval($data['ser']);
            $result = self::confirm($device, $serial);
            if (is_error($result)) {
                Log::error('fueling', [
                    'confirm' => $result,
                ]);
            }
        }
    }

    public static function hasUnpaidOrder(userModelObj $user): bool
    {
        $query = Order::query(['openid' => $user->getOpenid(), 'src' => Order::FUELING_UNPAID]);

        /** @var orderModelObj $order */
        foreach ($query->findAll() as $order) {
            if (!$order->isFuelingFinished()) {
                return true;
            }
        }

        return false;
    }
}