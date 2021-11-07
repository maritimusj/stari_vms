<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

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
use zovye\State;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\ZovyeException;
use function zovye\err;
use function zovye\error;
use function zovye\getArray;
use function zovye\is_error;
use function zovye\settings;

$op = request::op('default');
$order_no = request::str('orderNO');

if ($op == 'create_order_multi' && CtrlServ::checkJobSign(['orderNO' => $order_no])) {
    try {
        process($order_no);
    } catch (ExceptionNeedsRefund $e) {
        $device = $e->getDevice();
        if ($device) {
            refund($order_no, $device, $e->getMessage());
        }
    } catch (ZovyeException $e) {
        $device = $e->getDevice();
        if ($device) {
            $device->appShowMessage($e->getMessage(), 'error');
        }
        Util::logToFile('order_create_multi', [
            'orderNO' => $order_no,
            'error' => $e->getMessage(),
        ]);
    } catch (Exception $e) {
        Util::logToFile('order_create_multi', [
            'orderNO' => $order_no,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * @param $order_no
 * @return bool
 * @throws Exception
 */
function process($order_no): bool
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

    if (!$user->acquireLocker(User::ORDER_LOCKER)) {
        throw new Exception('用户无法锁定！');
    }

    //锁定设备
    $retries = intval(settings('device.lockRetries', 0));
    $delay = intval(settings('device.lockRetryDelay', 1));

    if (!$device->lockAcquire($retries, $delay)) {
        if (settings('order.waitQueue.enabled', false)) {
            if (!Job::createOrder($order_no, $device)) {
                throw new Exception('启动排队任务失败！');
            }
            return true;
        }
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

        $fail = 0;
        $success = 0;
        $is_pull_result_updated = false;

        $level = $pay_log->getData('level');
        $goods_list = $pay_log->getGoodsList();

        foreach ($goods_list as $goods) {
            for ($i = 0; $i < $goods['num']; $i++) {
                $result = pullGoods($order, $device, $user, $level, $goods);
                if (is_error($result) || !$is_pull_result_updated) {
                    $order->setResultCode($result['errno']);
                    $order->setExtraData('pull.result', $result);
                    if ($order->save()) {
                        $is_pull_result_updated = true;
                    }
                }
                if (is_error($result)) {
                    Util::logToFile('order_create_multi', [
                        'orderNO' => $order_no,
                        'error' => $result,
                    ]);
                    $fail++;
                } else {
                    $success++;
                }
            }
        }

        if (empty($success)) {
            ExceptionNeedsRefund::throwWith($device, '出货失败！');
        } elseif ($fail > 0) {
            $order->setExtraData('pull.result', err('部分商品出货失败！'));
            $order->save();

            //-1 表示失败的商品退款
            ExceptionNeedsRefund::throwWithN($device, -1,'部分商品出货失败！');
        }

        $order->setExtraData('pull.result', ['message' => '出货完成！']);
        $order->save();

        $device->appShowMessage('出货完成，欢迎下次使用！');
    }

    return true;
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
    $order_data = [
        'src' => intval($pay_log->getData('src', Order::PAY)),
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'num' => $pay_log->getTotal(),
        'price' => $pay_log->getPrice(),
        'balance' => 0,
        'ip' => $pay_log->getData('orderData.ip'),
        'extra' => [
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
        'result_code' => 0,
    ];

    if ($pay_log->isGoods()) {

        $goods_data = $pay_log->getGoods();
        $order_data['name'] = $goods_data['name'];
        $order_data['goods_id'] = $goods_data['id'];
        $order_data['extra']['goods'] = $goods_data;

    } elseif ($pay_log->isPackage()) {

        $package_data = $pay_log->getPackage();
        $order_data['name'] = $package_data['name'];
        $order_data['goods_id'] = 0; //使用 goods_id=0 表示该订单的商品是商品套餐
        $order_data['extra']['package'] = $package_data;

    } else {
        throw new Exception('找不到商品或者套餐信息！');
    }

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
    $user->remove('donate');

    return $order;
}

/**
 * @param orderModelObj $order
 * @param deviceModelObj $device
 * @param userModelObj $user
 * @param $level
 * @param $data
 * @return array
 */
function pullGoods(orderModelObj $order, deviceModelObj $device, userModelObj $user, $level, $data): array
{
    //todo 处理优惠券
    //$voucher = $pay_log->getVoucher();

    $goods = $device->getGoods($data['goods_id']);
    if (empty($goods)) {
        return err('找不到对应的商品！');
    }

    if ($goods['num'] < 1) {
        return err('对不起，商品库存不足！');
    }

    $pull_data = preparePullData($order, $device, $user);

    if ($goods['lottery']) {
        $mcb_channel = intval($goods['lottery']['size']);
        if ($goods['lottery']['index']) {
            $pull_data['index'] = intval($goods['lottery']['index']);
        }
    } else {
        $mcb_channel = Device::cargoLane2Channel($device, $goods['cargo_lane']);
    }

    if ($mcb_channel == Device::CHANNEL_INVALID) {
        return err('商品货道配置不正确！');
    }

    
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
        $locker = $device->payloadLockAcquire(3);
        if (empty($locker)) {
            return error(State::ERROR, '设备正忙，请重试！');
        }
        $res = $device->resetPayload([$goods['cargo_lane'] => -1], "订单：{$order->getOrderNO()}");
        if (is_error($res)) {
            return err('保存库存失败！');
        }
        $locker->unlock();
    }

    $device->save();

    $log_data = [
        'order' => $order->getId(),
        'result' => $result,
        'user' => $user->profile(),
        'goods' => $goods,
        'price' => $data['price'],
        'voucher' => isset($voucher) ? ['id' => $voucher->getId()] : [],
    ];

    $device->goodsLog($level, $log_data);

    if (!is_error($result)) {
        $device->updateRemain();
    }

    return $result;
}


function refund(string $order_no, deviceModelObj $device, string $reason)
{
    $need = Helper::NeedAutoRefund($device);
    if ($need) {
        $result = Job::refund($order_no, $reason, -1);
        if (empty($result) || is_error($result)) {
            Util::logToFile('order_create_multi', [
                'orderNO' => $order_no,
                'msg' => '启动退款任务！',
                'error' => $result,
            ]);
            $device->appShowMessage('退款失败，请联系客服，谢谢！', 'error');
        } else {
            $device->appShowMessage('正在退款，请稍后再试，谢谢！', 'error');
        }
    } else {
        $device->appShowMessage($reason, 'error');
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