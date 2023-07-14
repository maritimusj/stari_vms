<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$params = [
    'create' => true,
    'update' => true,
    'from' => [
        'src' => 'testing',
        'ip' => CLIENT_IP,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    ],
];

$user = Session::getCurrentUser($params);

if (empty($user) || $user->isBanned() || !$user->isTester()) {
    Response::alert('对不起，只有测试人员才能访问！', 'error');
}

$op = Request::op();
if (empty($op)) {
    Response::showTemplate('testing', [
        'api_url' => Util::murl('testing'),
        'jssdk' => Util::jssdk(),
    ]);
} else {
    if ($op == "detail") {
        $imei = Request::trim('imei');

        $device = Device::get($imei, true);
        if ($device && $device->isVDevice()) {
            JSON::success([
                'mcb' => [
                    'online' => true,
                    'signal' => 5,
                    'percent' => 100,
                ],
            ]);
        }

        $detail = CtrlServ::getV2("device/$imei");

        if (is_error($detail)) {
            JSON::fail('获取设备详情失败！');
        }

        if ($detail['status'] && $detail['data']['mcb']) {
            $detail['data']['mcb']['signal'] = intval(max(0, min(5, $detail['data']['mcb']['RSSI'] / 6)));
            $detail['data']['mcb']['percent'] = intval(max(0, min(100, $detail['data']['mcb']['RSSI'] / 31) * 100));
        }

        Response::json($detail['status'], $detail['data']);
    } else {
        if ($op == "test") {
            $imei = Request::trim('imei');
            $channel = Request::int('channel');

            if ($imei) {
                $x = Device::find($imei, ['imei', 'shadow_id']);
                if (empty($x)) {
                    JSON::fail(['lane' => $channel, 'msg' => '找不到这个设备,请重启设备！']);
                }
                if ($x->isVDevice()) {
                    JSON::success([
                        'lane' => $channel,
                        'msg' => "设备：$imei\r\n货道：$channel 出货成功！",
                    ]);
                }

                $deviceClassname = m('device')->objClassname();

                $device = new $deviceClassname(0, m('device'));
                $device->setImei($x->getImei());
                $device->setRemain(10);

                $device->clearDirty();

                $res = $device->pull([
                    'online' => true,
                    'channel' => $channel,
                    'from' => 'test',
                    'timeout' => settings('device.waitTimeout', DEFAULT_DEVICE_WAIT_TIMEOUT),
                ]);

                if (is_error($res)) {
                    JSON::fail([
                        'lane' => $channel,
                        'errno' => $res['errno'],
                        'msg' => $res['message'],
                    ]);
                }

                $x->setErrorCode(State::OK);
                $x->enableActiveQrcode(false);

                JSON::success([
                    'lane' => $channel,
                    'msg' => "设备：{$device->getImei()}\r\n货道：$channel 出货成功！",
                ]);
            }

            JSON::fail([
                'lane' => $channel,
                'msg' => '参数错误！',
            ]);
        }
    }
}
