<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\order;

use zovye\Advertising;
use zovye\Agent;
use zovye\App;
use zovye\CommissionBalance;
use zovye\Contract\ICard;
use zovye\CtrlServ;
use zovye\Device;
use zovye\Job;
use zovye\Locker;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\pay_logsModelObj;
use zovye\Order;
use zovye\request;
use zovye\UserCommissionBalanceCard;
use zovye\Util;
use zovye\VIPCard;
use zovye\Wx;
use function zovye\isEmptyArray;
use function zovye\request;
use function zovye\settings;


$id = request::int('id');

$log = [
    'id' => $id,
    'url' => $_SERVER['QUERY_STRING'],
];

$op = request::op('default');
if ($op == 'order' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $locker = Locker::try("order::statistics");
    if ($locker) {
        if ($id > 0) {
            $order = Order::get($id);
            if ($order) {
                if (Util::isSysLoadAverageOk()) {
                    Job::updateAppCounter();
                }

                $agent_id = $order->getAgentId();
                if ($agent_id) {
                    $agent = Agent::get($agent_id);
                    if ($agent) {
                        if (Util::isSysLoadAverageOk()) {
                            Job::updateAgentCounter($agent);
                        }
                        $agent->updateSettings('agentData.stats.last_order', [
                            'id' => $order->getId(),
                            'createtime' => $order->getCreatetime(),
                        ]);
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
                    'account' => $order->getAccount(),
                    'ip' => $order->getIp(),
                ];

                if ($device) {
                    if (Util::isSysLoadAverageOk()) {
                        Job::updateDeviceCounter($device);
                    }

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

                if ($device && isset($agent)) {
                    //通过微信模板消息给代理商推送消息
                    $tpl_id = settings('notice.order_tplid');
                    if ($tpl_id) {
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
                                    $type = '用户余额';
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
                            'keyword1' => ['value' => $price_formatted],
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

                $log['statistics'][$order->getId()] = Util::transactionDo(function () use ($order) {
                    return Util::orderStatistics($order);
                });

                if ($order->isFree() && App::isSponsorAdEnabled()) {
                    $data = $device->getOneAdv(Advertising::SPONSOR, true, function($adv) {
                       return $adv && $adv->getExtraData('num', 0) > 0;
                     });                     
                    if ($data) {
                        $adv = Advertising::get($data['id']);
                        if ($adv) {
                             $num =  $adv->getExtraData('num', 0);
                             $adv->setExtraData('num', max(0, $num - 1));
                             $adv->save();                            
                        }
                    }
                 }
            }
        }

        if (Util::isSysLoadAverageOk()) {
            //其它未处理订单
            $other_order = Order::query([
                'updatetime' => 0,
            ])->limit(100);

            $total = 0;
            /** @var orderModelObj $entry */
            foreach ($other_order->findAll() as $entry) {
                if ($entry && empty($entry->getUpdatetime())) {
                    $result = Util::transactionDo(function () use ($entry) {
                        return Util::orderStatistics($entry);
                    });
                    $log['statistics'][$entry->getId()] = $result ?: 'success';
                }
                $total++;
            }

            if ($total > 50) {
                Job::order(0);
            }
        }
    }
}
Log::debug('order', $log);