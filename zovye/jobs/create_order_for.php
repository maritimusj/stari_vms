<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\createOrderFor;

defined('IN_IA') or exit('Access Denied');

//创建订单
use Exception;
use zovye\CtrlServ;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\EventBus;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use zovye\util\Helper;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

$order_no = Request::str('orderNO');

$log = [
    'orderNO' => $order_no,
];

if (!CtrlServ::checkJobSign(['orderNO' => $order_no])) {
    throw new JobException('签名不正确！', $log);
}

if (!Locker::try("order_job:$order_no")) {
    throw new JobException('无法锁定订单！', $log);
}

$order = Order::get($order_no, true);
if (empty($order)) {
    throw new JobException('找不到这个订单！', $log);
}

if (!empty($order->getExtraData('pull.result'))) {
    throw new JobException('订单已出货！', $log);
}

$user = $order->getUser();

$device = $order->getDevice();
if (empty($device)) {
    throw new JobException('找不到这个设备！', $log);
}

//锁定设备
$retries = intval(settings('device.lockRetries', 0));
$delay = intval(settings('device.lockRetryDelay', 1));

if (!$device->lockAcquire($retries, $delay)) {
    if (settings('order.waitQueue.enabled', false)) {
        if (!Job::createOrderFor($order)) {
            throw new JobException('启动排队任务失败！', $log);
        }

        return true;
    }
    throw new JobException('设备被占用！', $log);
}

//事件：设备已锁定
try {
    EventBus::on(EVENT_LOCKED, [
        'device' => $device,
        'user' => $user,
    ]);
} catch (Exception $e) {
    $order->setExtraData('pull.result', err($e->getMessage()));
    $order->save();
    throw new JobException($e->getMessage(), $log);
}

$fail = 0;
$success = 0;

$level = intval($order->getExtraData('level', LOG_GOODS_FREE));
$goods_id = $order->getGoodsId();
$total = $order->getNum();

$goods = $device->getGoods($goods_id);

if (empty($goods) || $goods['num'] < $total) {
    $order->setExtraData('pull.result', err('商品库存不足！'));
    $order->save();
    throw new JobException('商品库存不足！', $log);
}

$log['error'] = [];
$goods['goods_id'] = $goods_id;

for ($i = 0; $i < $total; $i++) {
    $result = Helper::pullGoods($order, $device, $user, $level, $goods);
    if (is_error($result)) {
        $order->setResultCode($result['errno']);
        $order->setExtraData('pull.result', $result);
    }
    if (is_error($result)) {
        $log['error'][] = $result;
        $fail++;
    } else {
        $success++;
    }

    $order->setExtraData('stats', [
        'i' => $i + 1,
        'success' => $success,
        'fail' => $fail,
    ]);

    $order->save();
}

if ($fail > 0) {
    $order->setExtraData('pull.result', err('部分商品出货失败！'));
} else {
    $order->setExtraData('pull.result', [
        'errno' => 0,
        'message' => '出货完成！',
    ]);
}

$order->save();

$device->appShowMessage('出货完成，欢迎下次使用！');

//事件：出货成功，目前用于统计数据
try {
    EventBus::on(EVENT_OPEN_SUCCESS, [
        'device' => $device,
        'user' => $user,
        'order' => $order,
    ]);
} catch (Exception $e) {
    $log['device open success error'] = $e->getMessage();
}

Log::debug('create_order_for', $log);