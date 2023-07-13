<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\createOrderMulti;

use Exception;
use zovye\App;
use zovye\CtrlServ;
use zovye\DBUtil;
use zovye\Device;
use zovye\EventBus;
use zovye\ExceptionNeedsRefund;
use zovye\Helper;
use zovye\Job;
use zovye\Locker;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\pay_logsModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\Pay;
use zovye\Request;
use zovye\User;
use zovye\Util;
use zovye\ZovyeException;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

$op = Request::op('default');
$order_no = Request::str('orderNO');

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
        Log::error('order_create_multi', [
            'orderNO' => $order_no,
            'error' => $e->getMessage(),
        ]);
    } catch (Exception $e) {
        Log::error('order_create_multi', [
            'orderNO' => $order_no,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * @throws Exception
 */
function throwException(pay_logsModelObj $pay_log, $message, $refund = false, $device = null)
{
    $pay_log->setData('create_order.error', err($message));
    $pay_log->save();

    if ($refund && $pay_log->getPayName() != 'api') {
        if ($device) {
            ExceptionNeedsRefund::throwWith($device, $message);
        } else {
            ExceptionNeedsRefund::throw($message);
        }
    }

    throw new Exception($message);
}

/**
 * @param $order_no
 * @return bool
 * @throws Exception
 */
function process($order_no): bool
{
    $locker = Locker::try("pay:$order_no", REQUEST_ID, 6);
    if (!$locker) {
        throw new Exception('无法锁定支付信息！');
    }

    /** @var pay_logsModelObj $pay_log */
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        throw new Exception('找不到支付信息！');
    }

    if ($pay_log->isRefund()) {
        throw new Exception('支付已退款！');
    }

    $device = Device::get($pay_log->getDeviceId());
    if (empty($device)) {
        throwException($pay_log, '找不到指定的设备！', true);
    }

    $user = User::get($pay_log->getUserOpenid(), true);
    if (empty($user)) {
        throwException($pay_log, '找不到指定的用户！', true);
    }

    if (!$user->acquireLocker(User::ORDER_LOCKER)) {
        throwException($pay_log, '用户无法锁定！');
    }

    //锁定设备
    $retries = intval(settings('device.lockRetries', 0));
    $delay = intval(settings('device.lockRetryDelay', 1));

    if (!$device->lockAcquire($retries, $delay)) {
        if (settings('order.waitQueue.enabled', false)) {
            if (!Job::createOrder($order_no, $device)) {
                throwException($pay_log, '启动排队任务失败！');
            }

            return true;
        }
        throwException($pay_log, '设备被占用！');
    }

    if (Order::exists($order_no)) {
        throwException($pay_log, '订单已经存在！');
    }

    //事件：设备已锁定
    EventBus::on('device.locked', [
        'device' => $device,
        'user' => $user,
    ]);

    $orderResult = DBUtil::transactionDo(function () use ($order_no, $device, $user, $pay_log) {
        return createOrder($order_no, $device, $user, $pay_log);
    });

    if (is_error($orderResult)) {
        Log::error('order_create_multi', [
            'msg' => '创建订单失败！',
            'orderNO' => $order_no,
            'error' => $orderResult,
        ]);
        throwException($pay_log, $orderResult['message'], true, $device);
    } else {
        $locker->release();

        /** @var orderModelObj $order */
        $order = $orderResult;

        $fail = 0;
        $success = 0;
        $is_pull_result_updated = false;

        $level = intval($pay_log->getData('level'));
        $goods_list = $pay_log->getGoodsList();

        foreach ($goods_list as $goods) {
            for ($i = 0; $i < $goods['num']; $i++) {
                $result = Helper::pullGoods($order, $device, $user, $level, $goods);
                if (is_error($result)) {
                    Log::error('order_create_multi', [
                        'orderNO' => $order_no,
                        'error' => $result,
                    ]);
                    $fail++;
                } else {
                    $success++;
                }
                $order->setExtraData('pull.stats', [
                    'status' => 'pulling',
                    'success' => $success,
                    'fail' => $fail,
                    'num' => $i + 1,
                    'total' => $goods['num'],
                ]);
                if (is_error($result) || !$is_pull_result_updated) {
                    $order->setResultCode($result['errno']);
                    $order->setExtraData('pull.result', $result);
                    if ($order->save()) {
                        $is_pull_result_updated = true;
                    }
                }
                $order->save();
            }
        }

        if (empty($success)) {
            throwException($pay_log, '出货失败！', true, $device);
        } elseif ($fail > 0) {
            $order->setExtraData('pull.result', err('部分商品出货失败！'));
            $order->save();

            //-1 表示失败的商品退款
            throwException($pay_log, '部分商品出货失败！', true, $device);
        }

        $order->setExtraData('pull.stats.status', 'finished');
        $order->setExtraData('pull.result', [
            'errno' => 0,
            'message' => '出货完成！',
        ]);

        $order->save();

        $device->appShowMessage('出货完成，欢迎下次使用！');

        //事件：出货成功，目前用于统计数据
        EventBus::on('device.openSuccess', [
            'device' => $device,
            'user' => $user,
            'order' => $order,
        ]);
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
function createOrder(
    string $order_no,
    deviceModelObj $device,
    userModelObj $user,
    pay_logsModelObj $pay_log
): orderModelObj {
    $order_data = [
        'src' => intval($pay_log->getData('src', Order::PAY)),
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'num' => $pay_log->getTotal(),
        'price' => $pay_log->getPrice(),
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

    $qrcode = $pay_log->getData('qrcode.code', '');
    if ($qrcode) {
        $order_data['extra']['qrcode'] = $qrcode;
    }

    if (App::isGDCVMachineEnabled()) {
        $order_data['extra']['CV'] = [
            'profile' => $user->getIDCardVerifiedData(),
        ];
    }

    //定制功能：零佣金
    if (Helper::isZeroBonus($device, Order::PAY_STR)) {
        $order_data['agent_id'] = 0;
        $order_data['device_id'] = 0;
        $order_data['extra']['custom'] = [
            'zero_bonus' => true,
            'device' => $device->getId(),
            'agent' => $device->getAgentId(),
        ];
    }

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
    if ($query_result) {
        $query_result['from'] = 'query';
        $order_data['extra']['payResult'] = $query_result;
    } else {
        $pay_result = $pay_log->getPayResult();
        if (empty($pay_result)) {
            throw new Exception('订单未支付！');
        }
        $pay_result['from'] = 'cb';
        $order_data['extra']['payResult'] = $pay_result;
    }

    if (!empty($voucher)) {
        $order_data['src'] = Order::VOUCHER;
        $order_data['extra']['voucher'] = [
            'id' => $voucher->getId(),
        ];
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


function refund(string $order_no, deviceModelObj $device, string $reason)
{
    $need = Helper::NeedAutoRefund($device);
    if ($need) {
        $result = Job::refund($order_no, $reason, -1);
        if (empty($result)) {
            Log::error('order_create_multi', [
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
