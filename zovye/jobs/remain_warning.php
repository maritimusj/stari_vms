<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refund;

//设备缺货通知

use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;
use zovye\Request;
use zovye\SMSUtil;
use zovye\Util;
use zovye\Wx;
use function zovye\request;
use function zovye\settings;

$op = Request::op('default');
$log = [
    'id' => request('id'),
];
if ($op == 'remain_warning' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $device = Device::get(Request::int('id'));
    if ($device) {
        $agent = $device->getAgent();
        if ($agent) {
            $extra = $device->get('extra', []);

            //是否短信通知运营人员
            $reload_tp_lid = settings('notice.reload_smstplid');
            $warningRemain = settings('device.remainWarning', 1);

            if ($reload_tp_lid) {
                foreach ($device->getKeepers() as $keeper) {
                    if ($keeper && $keeper->getMobile()) {
                        $log['sms'][$keeper->getName()] = SMSUtil::send(
                            $keeper->getMobile(),
                            $reload_tp_lid,
                            [
                                'name' => $device->getName(),
                                'num' => "{$warningRemain}，剩余{$device->getRemainNum()}",
                            ]
                        );
                    }
                }
            }

            //通过微信模板消息给代理商推送消息
            $tpl_id = settings('notice.reload_tplid');
            if (!empty($tpl_id)) {

                $address = $extra['location']['address'] ?: '<未填写>';

                $payload = $device->getPayload();

                $lanes = [];
                foreach ($payload['cargo_lanes'] as $index => $lane) {
                    if ($lane['num'] < $warningRemain) {
                        $lanes[] = $index + 1;
                    }
                }

                if (empty($lanes)) {
                    $lanes_title = '全部货道';
                } else {
                    $lanes_title = '货道：'.implode(',', $lanes);
                }

                $notify_data = [
                    'first' => ['value' => '注意，设备商品数量不足！'],
                    'keyword1' => ['value' => "{$device->getName()}"],
                    'keyword2' => ['value' => $lanes_title],
                    'keyword3' => ['value' => $device->getRemainNum()],
                    'keyword4' => ['value' => $address],
                    'remark' => ['value' => "设备ID:{$device->getImei()}，".date('事件时间：Y-m-d H:i:s')],
                ];

                $log['data'] = $notify_data;

                foreach (Util::getNotifyOpenIds($agent, 'remainWarning') as $openid) {
                    $log['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $notify_data);
                }

                foreach ($device->getKeepers() as $keeper) {
                    $user = $keeper->getUser();
                    if ($user) {
                        $openid = $user->getOpenid();
                        $log['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $notify_data);
                    }
                }
                $device->updateLastRemainWarning();
            }
        }
    }
}

Log::debug('remain_warning', $log);
