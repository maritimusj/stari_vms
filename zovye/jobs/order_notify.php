<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refund;

//设备缺货通知

use zovye\CtrlServ;
use zovye\Device;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\model\pay_logsModelObj;
use zovye\Order;
use zovye\Request;
use zovye\UserCommissionBalanceCard;
use zovye\Util;
use zovye\VIPCard;
use zovye\Wx;
use function zovye\request;
use function zovye\settings;

$op = Request::op('default');
$log = [
    'id' => request('id'),
];

if ($op == 'order_notify' && CtrlServ::checkJobSign(['id' => request('id')])) {

    //通过微信模板消息给代理商推送消息
    $tpl_id = settings('notice.order_tplid');
    if ($tpl_id) {

        $order = Order::get(Request::int('id'));
        if (empty($order)) {
            throw new JobException('找不到这个订单！', $log);
        }

        $agent = $order->getAgent();
        if (!$agent) {
            throw new JobException('找不到所属代理商！', $log);
        }

        $device = $order->getDevice();
        if (!$device) {
            throw new JobException('找不到设备！', $log);
        }

        $price_formatted = number_format($order->getPrice() / 100, 2, '.', '') . '元';
        $num_formatted = $device->isFuelingDevice() ? $order->getNum() / 100 : $order->getNum();
        $goods = $order->getGoodsData();
        $type = '';
        if ($device->isFuelingDevice()) {
            if ($order->getSrc() == Order::FUELING_SOLO) {
                $type = '单机模式';
            } else {
                $card = $order->getExtraData('card', []);
                if ($card['type'] == UserCommissionBalanceCard::getTypename()) {
                    $type = '会员卡';
                } elseif ($card['type'] == pay_logsModelObj::getTypename()) {
                    $type = '现金支付';
                } elseif ($card['type'] == VIPCard::getTypename()) {
                    $type = 'VIP用户';
                } else {
                    $type = '其它方式';
                }
            }
        }

        $notify_data = [
            'first' => ['value' => '新订单已创建，详情如下：'],
            'keyword1' => ['value' => $price_formatted, 'color' => '#ff0000'],
            'keyword2' => ['value' => "$num_formatted{$goods['unit_title']}"],
            'keyword3' => ['value' => $device->getName()],
            'keyword4' => ['value' => $type],
            'keyword5' => ['value' => date('Y-m-d H:i:s', $order->getCreatetime())],
            'remark' => ['value' => "订单已经结算完成！"],
        ];

        $log['notify']['data'] = $notify_data;

        foreach (Util::getNotifyOpenIds($agent, 'order') as $openid) {
            $log['notify']['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $notify_data);
        }
    }
}

Log::debug('order_notify', $log);
