<?php

namespace zovye\job\createOrderMulti;

use Exception;
use zovye\App;
use zovye\CtrlServ;
use zovye\Device;
use zovye\model\deviceModelObj;
use zovye\EventBus;
use zovye\ExceptionNeedsRefund;
use zovye\Helper;
use zovye\request;
use zovye\Job;
use zovye\Order;
use zovye\model\orderModelObj;
use zovye\Pay;
use zovye\model\pay_logsModelObj;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Util;
use function zovye\err;
use function zovye\getArray;
use function zovye\is_error;
use function zovye\settings;

$op = request::op('default');
$order_no = request::str('orderNO');

if ($op == 'create_order_multi' && CtrlServ::checkJobSign(['orderNO' => $order_no])) {
    try {
        process($order_no);
    } catch (ExceptionNeedsRefund $e) {
        refund($order_no, $e->getNum(), $e->getDevice(), $e->getMessage());
    } catch (Exception $e) {
        Util::logToFile('order_create_multi', [
            'orderNO' => $order_no,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * @param $order_no
 * @throws Exception
 */
function process($order_no)
{
    /** @var pay_logsModelObj $pay_log */
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        throw new Exception('找不到支付信息！');
    }

    $pay_result = $pay_log->getPayResult();
    if (empty($pay_result)) {
        $query_result = $pay_log->getQueryResult();
        if (empty($query_result)) {
            throw new Exception('订单未支付！');
        }
    }

    $device = Device::get($pay_log->getDeviceId());
    if (empty($device)) {
        ExceptionNeedsRefund::throw('找不到指定的设备！');
    }

    $user = User::get($pay_log->getUserOpenid(), true);
    if (empty($user)) {
        ExceptionNeedsRefund::throwWith($device, '找不到指定的用户！');
    }

    if(!$user->lock()) {
        throw new Exception('用户无法锁定！');
    }

    //锁定设备
    $retries = intval(settings('device.lockRetries', 0));
    $delay = intval(settings('device.lockRetryDelay', 1));

    if (!$device->lockAcquire($retries, $delay)) {
        ExceptionNeedsRefund::throwWith($device, '设备被占用！');
    }
   
    if (Order::exists($order_no)) {
        throw new Exception('订单已经存在！');
    }

    //事件：设备已锁定
    EventBus::on('device.locked', [
        'device' => $device,
        'user' => $user,
    ]);

    $orderResult = Util::transactionDo(function () use ($order_no, $device, $user, $pay_log) {
        return createOrder($order_no, $device, $user, $pay_log);
    });

    if (is_error($orderResult)) {

        Util::logToFile('order_create_multi', [
            'msg' => '创建订单失败！',
            'orderNO' => $order_no,
            'error' => $orderResult,
        ]);

        ExceptionNeedsRefund::throwWith($device, $orderResult['message']);

    } else {
        /** @var orderModelObj $order */
        $order = $orderResult;

        //事件：出货成功，目前用于统计数据
        EventBus::on('device.openSuccess', [
            'device' => $device,
            'user' => $user,
            'order' => $order,
        ]);

        $total = $pay_log->getTotal();
        $success = 0;

        for ($i = 0; $i < $total; $i++) {

            $result = pullGoods($order, $device, $user, $pay_log);

            if (is_error($result)) {
                $order->setResultCode($result['errno']);
            } else {
                $order->setResultCode(0);
            }

            $order->setExtraData('pull.result', $result);
            $order->save();

            if (is_error($result)) {
                Util::logToFile('order_create_multi', [
                    'orderNO' => $order_no,
                    'error' => $result,
                ]);
            } else {
                $success++;
            }
        }

        if (empty($success)) {

            ExceptionNeedsRefund::throwWith($device, '出货失败！');

        } else if ($success < $total) {

            $order->setExtraData('pull.result', err('部分商品出货失败！'));
            $order->save();

            ExceptionNeedsRefund::throwWithN($device, $total - $success, '部分商品出货失败！');

        } else {
            $order->setExtraData('pull.result', ['message' => '出货完成！']);
            $order->save();
        }
    }
}

/**
 * @param string $order_no
 * @param deviceModelObj $device
 * @param userModelObj $user
 * @param pay_logsModelObj $pay_log
 * @return orderModelObj
 * @throws Exception
 */
function createOrder(string $order_no, deviceModelObj $device, userModelObj $user, pay_logsModelObj $pay_log): orderModelObj
{
    $goods_data = $pay_log->getGoods();

    $order_data = [
        'src' => Order::PAY,
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'name' => $goods_data['name'],
        'goods_id' => $goods_data['id'],
        'num' => $pay_log->getTotal(),
        'price' => $pay_log->getPrice(),
        'balance' => 0,
        'ip' => $pay_log->getData('orderData.ip'),
        'extra' => [
            'goods' => $goods_data,
            'discount' => [
                'total' => $pay_log->getDiscount(),
            ],
            'payResult' => [],
            'device' => [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ],
            'user' => $user->profile(),
        ],
    ];

    $query_result = $pay_log->getQueryResult();
    if (empty($query_result)) {
        $pay_result = $pay_log->getPayResult();
        $pay_result['from'] = 'cb';
        $order_data['extra']['payResult'] = $pay_result;
    } else {
        $query_result['from'] = 'query';
        $order_data['extra']['payResult'] = $query_result;
    }

    if (!empty($voucher)) {
        $order_data['src'] = Order::VOUCHER;
        $order_data['extra']['voucher'] = [
            'id' => $voucher->getId(),
        ];
    } else {
        $pay_type = getArray($order_data, 'extra.payResult.type');
        switch ($pay_type) {
            case Pay::SQM:
                $order_data['src'] = Order::SQM;
                break;
            default:
        }
    }

    $agent = $device->getAgent();
    if ($agent) {
        $order_data['extra']['agent'] = $agent->profile();
    }

    $order = Order::create($order_data);

    if (empty($order)) {
        throw new Exception('领取失败，创建订单失败！');
    }

    //事件：订单已经创建
    EventBus::on('device.orderCreated', [
        'device' => $device,
        'user' => $user,
        'order' => $order,

    ]);

    //保存在事件处理中存入订单的数据
    if (!$order->save()) {
        throw new Exception('领取失败，保存订单失败！');
    }

    $user->remove('last');

    return $order;
}

/**
 * @param orderModelObj $order
 * @param deviceModelObj $device
 * @param userModelObj $user
 * @param pay_logsModelObj $pay_log
 * @return array
 * @throws Exception
 */
function pullGoods(orderModelObj $order, deviceModelObj $device, userModelObj $user, pay_logsModelObj $pay_log): array
{
    //todo 处理优惠券
    //$voucher = $pay_log->getVoucher();

    $goods = $device->getGoods($pay_log->getGoodsId());
    if (empty($goods)) {
        return err('找不到对应的商品！');
    }

    if ($goods['num'] < 1) {
        return err('对不起，商品库存不足！');
    }

    if ($goods['lottery']) {
        $mcb_channel = intval($goods['lottery']['size']);
    } else {
        $mcb_channel = Device::cargoLane2Channel($device, $goods['cargo_lane']);
    }

    if ($mcb_channel == Device::CHANNEL_INVALID) {
        return err('商品货道配置不正确！');
    }

    $pull_data = preparePullData($order, $device, $user);
    $pull_data['channel'] = $mcb_channel;

    $result = $device->pull($pull_data);
    //v1版本新版本返回数据包含在json的data下
    if (is_error($result)) {
        $device->setError($result['errno'], $result['message']);
        $device->scheduleErrorNotifyJob($result['errno'], $result['message']);
    } elseif (is_error($result['data'])) {
        $device->setError($result['data']['errno'], $result['data']['message']);
        $device->scheduleErrorNotifyJob($result['data']['errno'], $result['data']['message']);
    } else {
        $device->resetPayload([$goods['cargo_lane'] => -1]);
    }
    $device->save();

    $log_data = [
        'order' => $order->getId(),
        'result' => $result,
        'user' => $user->profile(),
        'goods' => $goods,
        'voucher' => isset($voucher) ? ['id' => $voucher->getId()] : [],
    ];

    $device->goodsLog($pay_log->getData('level'), $log_data);

    $device->updateRemain();

    return $result;
}


function refund(string $order_no, int $num, deviceModelObj $device, string $reason)
{
    $need = Helper::NeedAutoRefund($device);
    if ($need) {
        $result = Job::refund($order_no, $reason, $num);
        if (empty($result) || is_error($result)) {
            Util::logToFile('order_create_multi', [
                'orderNO' => $order_no,
                'msg' => '启动退款任务！',
                'error' => $result,
            ]);
        }
    }
}

function preparePullData(orderModelObj $order, deviceModelObj $device, userModelObj $user): array
{
    $pull_data = [
        'online' => false,
        'timeout' => App::deviceWaitTimeout(),
        'userid' => $user->getOpenid(),
        'num' => $order->getNum(),
        'user-agent' => $order->getExtraData('from.user_agent'),
        'ip' => $order->getExtraData('from.ip'),
    ];

    $loc = $device->settings('extra.location', []);
    if ($loc && $loc['lng'] && $loc['lat']) {
        $pull_data['location'] = [
            'device' => [
                'lng' => $loc['lng'],
                'lat' => $loc['lat'],
            ],
        ];
    }

    return $pull_data;
}