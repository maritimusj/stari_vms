<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\order;

use zovye\Advertising;
use zovye\Agent;
use zovye\CtrlServ;
use zovye\Device;
use zovye\Locker;
use zovye\request;
use zovye\Job;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\Order;
use zovye\Util;
use function zovye\request;
use function zovye\isEmptyArray;
use function zovye\settings;

//订单后续处理

$id = request::int('id');

$log = [
    'id' => $id,
    'url' => $_SERVER['QUERY_STRING'],
];

$op = request::op('default');
if ($op == 'order' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $locker = Locker::try("order::statistics");
    if ($locker) {
        $order = Order::get($id);
        if ($order) {
            $agent_id = $order->getAgentId();
            if ($agent_id) {
                $agent = Agent::get($agent_id);
                if ($agent) {
                    $agent->updateSettings('agentData.stats.last_order', $order->getCreatetime());
                }
            }

            $device_id = $order->getDeviceId();

            /** @var deviceModelObj $device */
            $device = Device::get($device_id);

            //日志数据
            $log['order'] = [
                'goodsName' => $order->getExtraData('goods.name'),
                'num' => $order->getNum(),
                'price' => $order->getPrice(),
                'balance' => $order->getBalance(),
                'account' => $order->getAccount(),
                'ip' => $order->getIp(),
            ];

            if ($device) {
                $log['device'] = [
                    'name' => $device->getName(),
                    'imei' => $device->getImei(),
                    'remain' => $device->getRemainNum(),
                ];
            }

            if ($device && time() - $order->getCreatetime() < 30) {
                //是否自动清除错误代码
                if (settings('device.clearErrorCode')) {
                    $device->cleanError();
                    $device->save();
                }

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
            }

            $log['statistics'][$order->getId()] = Util::orderStatistics($order);
        }

        //其它未处理订单
        $other_order = Order::query([
            'updatetime' => 0,
        ])->limit(100);

        /** @var orderModelObj $entry */
        foreach ($other_order->findAll() as $entry) {
            if ($entry) {
                $log['statistics'][$entry->getId()] = Util::orderStatistics($entry) ?: 'success';
            }
        }

        Job::order(0);
    }
}
Util::logToFile('order', $log);
 