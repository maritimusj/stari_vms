<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\deviceError;

defined('IN_IA') or exit('Access Denied');

//设备出货故障通知

use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\Request;
use zovye\Util;
use zovye\Wx;
use function zovye\settings;

$op = Request::op('default');
$data = [
    'id' => Request::int('id'),
    'errno' => Request::int('errno'),
    'message' => Request::str('message'),
];

$log = [
    'data' => $data,
];

if ($op == 'device_err' && CtrlServ::checkJobSign($data)) {
    /** @var deviceModelObj $device */
    $device = Device::get(Request::int('id'));
    if ($device) {
        $error_msg = Request::str('message');
        //设置设备错误码
        $device->setError(Request::int('errno'), $error_msg);
        $device->save();

        $tpl_id = settings('notice.deviceerr_tplid');
        if (!empty($tpl_id)) {
            $data = [
                'first' => ['value' => '注意，设备出货异常！'],
                'keyword1' => ['value' => $device->getImei()],
                'keyword2' => ['value' => $device->getName()],
                'keyword3' => ['value' => date('Y-m-d H:i:s')],
                'keyword4' => ['value' => '设备出货失败：'.$error_msg],
            ];
            //通过微信模板消息给代理商推送消息
            $agent = $device->getAgent();
            if ($agent) {
                foreach (Util::getNotifyOpenIds($agent, 'deviceError') as $openid) {
                    $log['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $data);
                }

                foreach ($device->getKeepers() as $keeper) {
                    $user = $keeper->getUser();
                    if ($user) {
                        $openid = $user->getOpenid();
                        $log['result'][$openid] = Wx::sendTplNotice($openid, $tpl_id, $data);
                    }
                }
                $device->updateLastDeviceNotify();
            }
        }
    }
}

Log::debug('device_err', $log);
