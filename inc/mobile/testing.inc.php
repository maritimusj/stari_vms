<?php

namespace zovye;

$params = [
    'create' => true,
    'update' => true,
    'from' => [
        'src' => 'testing',
        'ip' => CLIENT_IP,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    ],
];

$user = Util::getCurrentUser($params);

if (empty($user) || $user->isBanned() || !$user->isTester()) {
    Util::resultAlert('对不起，只有测试人员才能访问！', 'error');
}

$op = request::op();
if (empty($op)) {
    $this->showTemplate('testing', [
        'api_url' => Util::murl('testing'),
        'jssdk' => Util::fetchJSSDK(),
    ]);
} else if ($op == "detail") {
    $imei = request::trim('imei');

    $device = Device::get($imei, true);
    if ($device && $device->isVDevice()) {
        JSON::success([
            'mcb' => [
                'online' => true,
                'signal' => 5,
                'percent' => 100,
            ]
        ]);
    }

    $detail = CtrlServ::v2_query("device/{$imei}");
    
    if (is_error($detail)) {
        JSON::fail('获取设备详情失败！' );
    }

    if ($detail['status'] && $detail['data']['mcb']) {
        $detail['data']['mcb']['signal'] =  intval(max(0, min(5, $detail['data']['mcb']['RSSI'] / 6)));
        $detail['data']['mcb']['percent'] =  intval(max(0, min(100, $detail['data']['mcb']['RSSI'] / 31) * 100));
    }

    Util::resultJSON($detail['status'], $detail['data']);
} else if ($op == "test") {
    $imei    = request::trim('imei');
    $channel = request::int('channel');

    if ($imei) {
        $x = Device::find($imei, ['imei', 'shadow_id']);
        if (empty($x)) {
            Util::resultJSON(false, ['lane' => $channel, 'msg' => '找不到这个设备,请重启设备！']);
        }
        if ($x->isVDevice()) {
            JSON::success([
                'lane' => $channel,
                'msg' => "设备：{$imei}\r\n货道：{$channel} 出货成功！",
            ]);
        }

        $deviceClassname = m('device')->objClassname();

        $device = new $deviceClassname(0, m('device'));
        $device->setImei($x->getImei());
        $device->setRemain(10);

        $device->clearDirty();

        $res = $device->pull([
            'online'  => true,
            'channel' => $channel,
            'from'    => 'test',
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
            'msg' => "设备：{$device->getImei()}\r\n货道：{$channel} 出货成功！",
        ]);
    }

    JSON::fail([
        'lane' => $channel,
        'msg' => '参数错误！'
    ]);
}
