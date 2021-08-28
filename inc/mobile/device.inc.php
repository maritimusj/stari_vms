<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use Exception;

$op = request::op('default');

if ($op == 'default') {

    //检查设备
    $device_id = request('id'); //设备ＩＤ
    if (empty($device_id)) {
        Util::resultAlert('请扫描设备二维码，谢谢！', 'error');
    }

    $device = Device::find($device_id, ['imei', 'shadow_id']);
    if (empty($device)) {
        Util::resultAlert('设备二维码不正确！', 'error');
    }

    //开启了shadowId的设备，只能通过shadowId找到
    if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $device_id) {
        Util::resultAlert('设备二维码不正确，请重新扫描！', 'error');
    }

    header('location:' . Util::murl('entry', ['from' => 'device', 'device' => $device->getShadowId()]));

} else if ($op == 'fb_pic') {

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    We7::load()->func('file');
    $res = We7::file_upload($_FILES['pic']);

    if (!is_error($res)) {
        $filename = $res['path'];
        if ($res['success'] && $filename) {
            try {
                We7::file_remote_upload($filename);
            } catch (Exception $e) {
                Util::logToFile('mobile_device_fb', $e->getMessage());
            }
        }
        $url = $filename;
        JSON::success(['data' => $url]);
    } else {
        JSON::fail(['mst' => '上传失败！']);
    }

} else if ($op == 'feed_back') {

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    $device_imei = request::str('device');
    $device = Device::get($device_imei, true);

    if (!$device) {
        JSON::fail('找不到这台设备！');
    }

    $text = request::trim('text');
    $pics = request::array('pics');

    if (empty($text)) {
        JSON::fail('请输入反馈内容！');
    }

    $data = [
        'device_id' => $device->getId(),
        'user_id' => $user->getId(),
        'text' => $text,
        'pics' => serialize($pics),
        'createtime' => time(),
    ];

    if (m('device_feedback')->create($data)) {
        JSON::success('反馈成功！');
    } else {
        JSON::fail('反馈失败！');
    }

} elseif ($op == 'poll_event') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $set_data = $device->settings('weigh');
    if (is_null($set_data)) {
        $set_data = [];
    }
    if (!isset($set_data['price'])) {
        $set_data['price'] = ['0', '0', '0', '0', '0', '0', '0', '0', '0', '0'];
    }
    if (!isset($set_data['mod'])) {
        $set_data['mod'] = '制冷';
    }

    $arr = [
        'd_uid' => '',
        'per' => 0,
        'm_mod' => $set_data['mod'],
        'sw' => [],
        'door' => [],
        'temperature' => 0,
        'weights' => [],
        'm_price' => $set_data['price'],
    ];

    $arr['d_uid'] = substr($device->getUid(), -5);

    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 14');
    $query->orderBy('id DESC');
    $res = $query->findOne();
    $the_14th_id = 0;
    if ($res) {
        $the_14th_id = $res->getId();
        $extra = json_decode($res->getExtra(), true);
        $extra = $extra['extra'];

        $rssi = $extra['RSSI'] ?: 0;
        $per = floor($rssi * 100 / 31);

        $arr['per'] = $per;
    }

    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 20');
    $query->orderBy('id DESC');
    $res = $query->findOne();
    $the_20th_id = 0;

    if ($res) {
        $the_20th_id = $res->getId();
        $extra = json_decode($res->getExtra(), true);
        $extra = $extra['extra'];

        $sw = $extra['sw'] ?: [];
        $f_sw = [];
        foreach ($sw as $val) {
            if ($val == 1) {
                $f_sw[] = '工作';
            } else {
                $f_sw[] = '空闲';
            }
        }
        $door = $extra['door'] ?: [];
        $f_door = [];
        foreach ($door as $val) {
            if ($val == 1) {
                $f_door[] = '关';
            } else {
                $f_door[] = '开';
            }
        }

        $arr['sw'] = $f_sw;
        $arr['door'] = $f_door;
        $arr['temperature'] = $extra['temperature'];
        $arr['weights'] = $extra['weights'];
    }

    //
    $the_21th_id = 0;
    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 21');
    $query->orderBy('id DESC');
    $res = $query->findOne();
    if ($res) {
        $the_21th_id = $res->getId();
    }

    $tpl_data['the_14th_id'] = $the_14th_id;
    $tpl_data['the_20th_id'] = $the_20th_id;
    $tpl_data['the_21th_id'] = $the_21th_id;
    $tpl_data['arr'] = $arr;
    $tpl_data['device'] = $device;

    app()->showTemplate(Theme::file('poll_event'), $tpl_data);

} elseif ($op == 'new_event') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $the_14th_id = request::int('the_14th_id');
    $the_20th_id = request::int('the_20th_id');
    $the_21th_id = request::int('the_21th_id');

    $arr = [];

    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 14');
    $query->where('id > ' . $the_14th_id);
    $query->orderBy('id DESC');
    $res = $query->findOne();
    if ($res) {
        $the_14th_id = $res->getId();
        $extra = json_decode($res->getExtra(), true);
        $extra = $extra['extra'];

        $rssi = $extra['RSSI'] ?: 0;
        $per = floor($rssi * 100 / 31);

        $arr['per'] = $per;
    }

    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 20');
    $query->where('id > ' . $the_20th_id);
    $query->orderBy('id DESC');
    $res = $query->findOne();
    if ($res) {
        $the_20th_id = $res->getId();
        $extra = json_decode($res->getExtra(), true);
        $extra = $extra['extra'];

        $sw = $extra['sw'] ?: [];
        $f_sw = [];
        foreach ($sw as $val) {
            if ($val == 1) {
                $f_sw[] = '工作';
            } else {
                $f_sw[] = '空闲';
            }
        }
        $door = $extra['door'] ?: [];
        $f_door = [];
        foreach ($door as $val) {
            if ($val == 1) {
                $f_door[] = '关';
            } else {
                $f_door[] = '开';
            }
        }

        $arr['sw'] = $f_sw;
        $arr['door'] = $f_door;
        $arr['temperature'] = $extra['temperature'];
        $arr['weights'] = $extra['weights'];
    }

    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 21');
    $query->where('id > ' . $the_21th_id);
    $query->orderBy('id DESC');
    $res = $query->findOne();
    if ($res) {
        //开门指令
        $extra = json_decode($res->getExtra(), true);
        $extra = $extra['extra'];

        $body = [];
        if ($extra['door'] == 1) {
            $body['door'] = [1, 0];
        }

        if ($extra['door'] == 2) {
            $body['door'] = [0, 1];
        }

        $uid = $device->getUid();
        CtrlServ::v2_query("wdevice/{$uid}", [], $body);
    }

    echo json_encode(['arr' => $arr, 'the_14th_id' => $the_14th_id, 'the_20th_id' => $the_20th_id]);

} elseif ($op == 'set_event') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 20');
    $query->orderBy('id DESC');
    $res = $query->findOne();
    $the_20th_id = 0;
    $arr = [];
    if ($res) {
        $the_20th_id = $res->getId();
        $extra = json_decode($res->getExtra(), true);
        $extra = $extra['extra'];
        $arr['sw'] = $extra['sw'] ?: [];
        $arr['door'] = $extra['door'] ?: [];
        $arr['temperature'] = $extra['temperature'];
    }

    $set_data = $device->settings('weigh');
    if (is_null($set_data)) {
        $set_data = [];
    }
    if (!isset($set_data['mod'])) {
        $set_data['mod'] = '制冷';
    }

    //
    $the_21th_id = 0;
    $query = $device->eventQuery(['device_uid' => $device->getUid()]);
    $query->where('event = 21');
    $query->orderBy('id DESC');
    $res = $query->findOne();
    if ($res) {
        $the_21th_id = $res->getId();
    }

    $tpl_data = [];
    $tpl_data['arr'] = $arr;
    $tpl_data['set_data'] = $set_data;
    $tpl_data['the_20th_id'] = $the_20th_id;
    $tpl_data['the_21th_id'] = $the_21th_id;
    $tpl_data['device'] = $device;

    app()->showTemplate(Theme::file('set_event'), $tpl_data);


} elseif ($op == 'save_set') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $set_data = $device->settings('weigh');
    if (is_null($set_data)) {
        $set_data = [];
    }

    $uid = $device->getUid();
    //$code = $device->getProtocolV1Code();

    $type = request::str('type');

    if ($type == 'door') {
        $door = request::int('door');

        $body = [];
        if ($door == 0) {
            $body['door'] = [1, 0];
        }

        if ($door == 1) {
            $body['door'] = [0, 1];
        }

        $res = CtrlServ::v2_query("wdevice/{$uid}", [], $body);
        echo json_encode($res);

    } elseif ($type == 'sw') {
        $val = request::int('val');
        $body = [
            'sw03' => $val
        ];

        $res = CtrlServ::v2_query("wdevice/{$uid}", [], $body);
        echo json_encode($res);

    } elseif ($type == 'mode') {
        $val = request::int('val');
        if ($val == 1) {
            $set_data['mod'] = '制热';
        } else {
            $set_data['mod'] = '制冷';
        }

        $body = [
            'mode' => $val
        ];

        $res = CtrlServ::v2_query("wdevice/{$uid}", [], $body);
        if ($res['status']) {
            $device->updateSettings('weight', $set_data);
        }
        echo json_encode($res);

    } elseif ($type == 'temp') {
        $set_data['t1'] = request::int('t1');
        $set_data['t2'] = request::int('t2');

        $body = [
            'low' => $set_data['t1'],
            'high' => $set_data['t2'],
        ];

        $res = CtrlServ::v2_query("wdevice/{$uid}", [], $body);
        if ($res['status']) {
            $device->updateSettings('weight', $set_data);
        }
        echo json_encode($res);

    } elseif ($type == 'cc') {

        $num = request::int('num');
        $res = CtrlServ::v2_query("wdevice/{$uid}/reset", ['num' => $num, 'timeout' => 0]);
        echo json_encode($res);

    } elseif ($type == 'price') {

        if (!isset($set_data['price'])) {
            $set_data['price'] = ['0', '0', '0', '0', '0', '0', '0', '0', '0', '0'];
        }
        if (request::isset('p0')) {
            $set_data['price'][0] = strval(abs(request::float('p0', 0, 2)));
        }
        if (request::isset('p1')) {
            $set_data['price'][1] = strval(abs(request::float('p1', 0, 2)));
        }
        if (request::isset('p2')) {
            $set_data['price'][2] = strval(abs(request::float('p2', 0, 2)));
        }
        if (request::isset('p3')) {
            $set_data['price'][3] = strval(abs(request::float('p3', 0, 2)));
        }
        if (request::isset('p4')) {
            $set_data['price'][4] = strval(abs(request::float('p4', 0, 2)));
        }
        if (request::isset('p5')) {
            $set_data['price'][5] = strval(abs(request::float('p5', 0, 2)));
        }
        if (request::isset('p6')) {
            $set_data['price'][6] = strval(abs(request::float('p6', 0, 2)));
        }
        if (request::isset('p7')) {
            $set_data['price'][7] = strval(abs(request::float('p7', 0, 2)));
        }
        if (request::isset('p8')) {
            $set_data['price'][8] = strval(abs(request::float('p8', 0, 2)));
        }
        if (request::isset('p9')) {
            $set_data['price'][9] = strval(abs(request::float('p9', 0, 2)));
        }

        $body = [
            'disp' => $set_data['price'],
        ];

        $res = CtrlServ::v2_query("wdevice/{$uid}", [], $body);
        if ($res['status']) {
            $device->updateSettings('weight', $set_data);
        }

        $res['price'] = $set_data['price'];
        echo json_encode($res);
    }

} else if ($op == 'detail') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $detail = $device->getOnlineDetail();
    if ($detail && $detail['mcb'] && $detail['mcb']['online']) {
        $device->updateSettings('last.online', time());
    } else {
        $device->updateSettings('last.online', 0);
    }

    $device->save();

    JSON::success($detail);

} else if ($op == 'is_ready') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $is_ready = false;

    $scene = request::str('scene');
    if ($scene == 'online') {
        $is_ready = $device->isMcbOnline(false);
    } elseif ($scene == 'lock') {
        if (!$device->isLocked()) {
            if (Locker::try("device:is_ready:{$device->getId()}")) {
                $is_ready = true;
            }
        }
    }

    JSON::success([
        'is_ready' => $is_ready,
    ]);

} elseif ($op == 'goods') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    $result = $device->getGoodsAndPackages($user, ['allowPay']);
    JSON::success($result);
}