<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\DeviceOnline;

defined('IN_IA') or exit('Access Denied');

//设备上线通知

use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;
use zovye\Request;
use zovye\Util;
use zovye\Wx;
use function zovye\request;
use function zovye\settings;

$op = Request::op('default');
$data = [
    'id' => request('id'),
    'event' => request('event'),
];

$log = [
    'data' => $data,
];

if ($op == 'device_online' && CtrlServ::checkJobSign($data)) {
    $device = Device::get(Request::int('id'));
    if ($device) {
        $tpl_id = settings('notice.deviceOnline_tplid');
        if (!empty($tpl_id)) {
            $data = [
                'first' => ['value' => '注意，设备已'.$data['event'].'!'],
                'keyword1' => ['value' => $device->getName()],
                'keyword2' => ['value' => $data['event']],
                'keyword3' => ['value' => date('Y-m-d H:i:s')],
            ];
            //通过微信模板消息给代理商推送消息
            $agent = $device->getAgent();
            if ($agent) {
                foreach (Util::getNotifyOpenIds($agent, 'deviceOnline') as $openid) {
                    $log['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $data);
                }
            }
            foreach ($device->getKeepers() as $keeper) {
                $user = $keeper->getUser();
                if ($user) {
                    $openid = $user->getOpenid();
                    $log['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $data);
                }
            }
            $device->updateLastDeviceOnlineNotify();
        }
    }
}

Log::debug('device_online', $log);
