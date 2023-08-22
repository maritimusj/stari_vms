<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\createOrder;

defined('IN_IA') or exit('Access Denied');

//创建订单
use Exception;
use zovye\App;
use zovye\base\modelObj;
use zovye\CtrlServ;
use zovye\DBUtil;
use zovye\Device;
use zovye\EventBus;
use zovye\ExceptionNeedsRefund;
use zovye\Helper;
use zovye\Job;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goods_voucher_logsModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\Pay;
use zovye\Request;
use zovye\User;
use zovye\ZovyeException;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

$op = Request::op('default');
$order_no = Request::str('orderNO');

if ($op == 'create_order' && CtrlServ::checkJobSign(['orderNO' => $order_no])) {
    try {
        prepare($order_no);
    } catch (ExceptionNeedsRefund $e) {
        $device = $e->getDevice();
        $refund = Helper::NeedAutoRefund($device);
        if ($refund) {
            $res = Job::refund($order_no, $e->getMessage(), 0, false, intval(settings('order.rollback.delay', 0)));
            if (!$res) {
                $device->appShowMessage('退款失败，请联系客服，谢谢！');
                Log::fatal('order_create', [
                    'orderNO' => $order_no,
                    'msg' => '启动退款任务失败！',
                ]);
            } else {
                $device->appShowMessage('正在退款，请稍后再试，谢谢！');
            }
        }
        Log::fatal('order_create', [
            'orderNO' => $order_no,
            'refund' => $refund,
            'error' => $e->getMessage(),
        ]);

    } catch (ZovyeException $e) {
        $device = $e->getDevice();
        if ($device) {
            $device->appShowMessage($e->getMessage(), 'error');
        }
        Log::error('order_create', [
            'orderNO' => $order_no,
            'error' => $e->getMessage(),
        ]);
    } catch (Exception $e) {
        Log::error('order_create', [
            'orderNO' => $order_no,
            'refund' => false,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * @param string $order_no
 * @throws Exception
 */
function prepare(string $order_no)
{
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        throw new Exception('找不到支付信息！');
    }

    $pay_result = $pay_log->getQueryResult();
    if ($pay_result) {
        $pay_result['from'] = 'query';
    } else {
        $pay_result = $pay_log->getPayResult();
        if (empty($pay_result)) {
            throw new Exception('订单未支付！');
        }
        $pay_result['from'] = 'cb';
    }

    $device = Device::get($pay_log->getDeviceId());
    if (empty($device)) {
        ExceptionNeedsRefund::throw('找不到指定的设备！');
    }

    $user = User::get($pay_log->getUserOpenid(), true);
    if (empty($user)) {
        ExceptionNeedsRefund::throwWith($device, '找不到指定的用户！');
    }

    if (!$user->acquireLocker('create::order')) {
        throw new Exception('用户无法锁定！');
    }

    $eventData = [
        'device' => $device,
        'user' => $user,
    ];

    //锁定设备
    $retries = intval(settings('device.lockRetries', 0));
    $delay = intval(settings('device.lockRetryDelay', 1));

    if (!$device->lockAcquire($retries, $delay)) {
        ExceptionNeedsRefund::throwWith($device, '设备被占用！');
    }

    if (Order::exists($order_no)) {
        throw new Exception('订单已经存在！');
    }

    $goods = $device->getGoods($pay_log->getGoodsId());
    if (empty($goods)) {
        ExceptionNeedsRefund::throwWith($device, '找不到对应的商品！');
    }

    if ($goods['num'] < 1) {
        ExceptionNeedsRefund::throwWith($device, '对不起，商品库存为零！');
    }

    //事件：设备已锁定
    EventBus::on('device.locked', $eventData);

    $log_data = [
        'user' => $user->profile(),
        'goods' => $goods,
        'payload' => $device->getPayload(),
        'account' => isset($acc) ? ['name' => $acc->name(), 'title' => $acc->title()] : [],
        'voucher' => isset($voucher) ? ['id' => $voucher->getId()] : [],
    ];

    $params = [
        'src' => intval($pay_log->getData('src', Order::PAY)),
        'device' => $device,
        'user' => $user,
        'ip' => strval($pay_log->getData('orderData.ip')),
        'payResult' => [
            'type' => $pay_result['type'],
            'result' => 'success',
            'from' => $pay_result['from'],
            'total' => $pay_result['total'],
            'transaction_id' => $pay_result['transaction_id'],
        ],
        'transaction_id' => strval($pay_result['transaction_id']),
    ];

    DBUtil::transactionDo(function () use (&$params, $order_no, $goods, &$log_data) {

        list($result, $order) = createOrder($params, $order_no, $goods);

        if (is_error($result)) {
            $log_data['result'] = $result;
        } else {
            $log_data['result'] = $order->getExtraData('pull.result', []);
        }

        if ($order) {
            $params['order'] = $order;
        }

        //返回true，则失败也会创建订单
        return true;
    });

    //事件：出货成功
    EventBus::on('device.openSuccess', $params);

    $device->updateAppRemain();

    $level = intval($pay_log->getData('level'));
    $device->goodsLog($level, $log_data);

    if (is_error($log_data['result'])) {
        ExceptionNeedsRefund::throwWith($device, $log_data['result']['message']);
    }

    Log::debug('create_order', $log_data);
}

/**
 * @param array $params
 * @param string $order_no
 * @param array $goods
 * @return array
 */
function createOrder(array $params, string $order_no, array $goods): array
{
    /** @var deviceModelObj $device */
    $device = $params['device'];

    /** @var userModelObj $user */
    $user = $params['user'];

    /** @var accountModelObj $acc */
    $acc = $params['account'];

    /** @var goods_voucher_logsModelObj $voucher */
    $voucher = $params['voucher'];

    $order_data = [
        'src' => $params['src'],
        'order_id' => $order_no,
        'transaction_id' => $params['transaction_id'],
        'openid' => $user->getOpenid(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'name' => $goods['name'],
        'goods_id' => $goods['id'],
        'num' => 1,
        'price' => $goods['price'],
        'account' => empty($acc) ? '' : $acc->name(),
        'ip' => $params['ip'],
        'extra' => [
            'goods' => $goods,
            'discount' => [
                'total' => User::getUserDiscount($user, $goods),
            ],
            'payResult' => $params['payResult'],
            'device' => [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ],
            'user' => $user->profile(),
        ],
    ];

    if (App::isGDCVMachineEnabled()) {
        $order_data['extra']['CV'] = [
            'profile' => $user->getIDCardVerifiedData(),
        ];
    }

    if ($voucher) {
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
        return [err('领取失败，创建订单失败')];
    }

    unset($params['src']);
    unset($params['ip']);

    $params['order'] = $order;

    try {
        //事件：订单已经创建
        EventBus::on('device.orderCreated', $params);
    } catch (Exception $e) {
        return [err($e->getMessage())];
    }

    $user->remove('last');

    foreach ($params as $entry) {
        if ($entry instanceof modelObj && !$entry->save()) {
            return [err('无法保存数据，请重试')];
        }
    }

    $pull_data = Helper::preparePullData($order, $device, $user, $goods);
    if (is_error($pull_data)) {
        return [$pull_data];
    }

    $order->setExtraData('device.ch', $pull_data['channel']);

    //请求出货
    $result = $device->pull($pull_data);

    if (is_error($result)) {
        $order->setResultCode($result['errno']);
    } else {
        $order->setResultCode(0);
    }

    //记录出货结果
    $order->setExtraData('pull.result', $result);

    if ($device->isBlueToothDevice()) {
        $order->setBluetoothDeviceBUID($device->getBUID());
    }

    if (!$order->save()) {
        return [err('无法保存订单数据！')];
    }

    if (is_error($result)) {
        try {
            //事件：出货失败
            EventBus::on('device.openFail', $params);
        } catch (Exception $e) {
        }
    } else {
        //使用取货码
        if ($voucher) {
            $voucher->setUsedUserId($user->getId());
            $voucher->setUsedtime(time());
            if (!$voucher->save()) {
                return [err('使用取货码失败！')];
            }
        }
    }

    //处理库存
    if ((settings('device.errorInventoryOp') || !is_error($result)) && isset($goods['cargo_lane'])) {
        $locker = $device->payloadLockAcquire(3);
        if (empty($locker)) {
            return [err('设备正忙，请重试！')];
        }
        $result = $device->resetPayload([$goods['cargo_lane'] => -1], "订单：$order_no");
        if (is_error($result)) {
            return [err('保存库存变动失败！')];
        }
        $locker->unlock();
    }

    $device->save();

    /**
     * 始终返回 true，是为了即使失败，仍然创建订单
     */
    return [$result, $order];
}


