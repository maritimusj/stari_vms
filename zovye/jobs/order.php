<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\order;

defined('IN_IA') or exit('Access Denied');

use zovye\App;
use zovye\CtrlServ;
use zovye\domain\Advertising;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use zovye\util\Helper;
use zovye\util\Util;
use zovye\We7;
use function zovye\isEmptyArray;
use function zovye\settings;

$id = Request::int('id');

$log = [
    'id' => $id,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$order = Order::get($id);
if (!$order) {
    throw new JobException('找不到这个订单！', $log);
}

$device = $order->getDevice();
if (!$device) {
    throw new JobException('找不到这个设备！', $log);
}

//是否自动清除错误代码
if (settings('device.clearErrorCode') && $order->isResultOk()) {
    $device->cleanLastError();
}

if (We7::pdo_field_exists(APP_NAME.'_device', 'last_order')) {
    $device->setLastOrder($order->getCreatetime());
}

$device->save();

//检查剩余商品数量
$device->checkRemain();

//检查公众号消息推送设置
$media = null;
$adv = $device->getOneAdv(Advertising::PUSH_MSG, true);
if ($adv) {
    $media = [
        'type' => $adv['extra']['msg']['type'],
        'val' => $adv['extra']['msg']['val'],
        'delay' => intval($adv['extra']['delay']),
    ];
}

//使用全局默认设置
if (isEmptyArray($media)) {
    $media = [
        'type' => settings('misc.pushAccountMsg_type'),
        'val' => settings('misc.pushAccountMsg_val'),
        'delay' => settings('misc.pushAccountMsg_delay'),
    ];
}

if ($media && $media['type'] != 'settings' && $media['type'] != 'none' && $media['val'] != '') {
    $media['touser'] = $order->getOpenid();
    $log['accountMsg_res'] = Job::accountMsg($media);
}

if ($order->isFree() && App::isSponsorAdEnabled()) {
    $data = $device->getOneAdv(Advertising::SPONSOR, true, function ($ad) {
        return $ad && $ad->getExtraData('num', 0) > 0;
    });
    if ($data) {
        $ad = Advertising::get($data['id']);
        if ($adv) {
            $num = $ad->getExtraData('num', 0);
            $ad->setExtraData('num', max(0, $num - 1));
            $ad->save();
        }
    }
}

$agent = $order->getAgent();
if ($agent) {
    $agent->updateSettings('agentData.stats.last_order', [
        'id' => $order->getId(),
        'createtime' => $order->getCreatetime(),
    ]);
}

if (Locker::try("order::statistics")) {
    if (Util::isSysLoadAverageOk()) {
        Job::updateAppCounter();
    }

    if ($agent && Util::isSysLoadAverageOk()) {
        Job::updateAgentCounter($agent);
    }

    if (Util::isSysLoadAverageOk()) {
        Job::updateDeviceCounter($device);
    }

    if (Util::isSysLoadAverageOk()) {
        Helper::orderStatistics($order);
    }
}

Log::debug('order', $log);