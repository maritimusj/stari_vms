<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
if ($op == 'sms') {

    try {
        $mobile = request::trim('mobile');

        if (empty($mobile)) {
            throw new RuntimeException('Invalid mobile phone number!');
        }
    
        /** userModelObj $user */
        $user = User::getOrCreate($mobile, User::PROMO);
        if (empty($user)) {
            throw new RuntimeException('Fail to get user info!');
        }
    
        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            throw new RuntimeException('An error occurred, please try again later!');
        }
    
        $device = Device::get(request::str('device'), true);
        if (empty($device)) {
            throw new RuntimeException('Fail to get device info!');
        }
    
        if (!$device->lockAcquire()) {
            throw new RuntimeException('An error occurred, please try again later!');
        }
    
        $res = Promo::verifySMS($user);
        if (is_error($res)) {
            throw new RuntimeException($res['message']);
        }
    
        $code = Promo::getSMSCode();

        $res = (new ChuanglanSmsApi())->send($mobile, $code);
        if (is_error($res)) {
            throw new RuntimeException($res['message']);
        }

        if (!empty($res['code'])) {
            throw new RuntimeException("Fail to send sms: {$res['error']}");
        }

        if (!Promo::createSMSLog($user, [
            'code' => $code,
            'result' => $res,
            'device' => $device->getImei(),
            'createtime' => time(),
        ])) {
            throw new RuntimeException('An error occurred, please try again later!');
        }
        
        JSON::success(['code' => $code]); //code here for debug

    } catch(RuntimeException $e) {
        JSON::fail($e);
    }

    
} elseif ($op == 'order') {
    
    $mobile = request::str('mobile');
    $code = request::str('code');
    $num = request::int('num', 1);

    $config = Promo::getConfig();

    if (empty($mobile) || empty($code)) {
        JSON::fail('Invalid request params.');
    }

    $user = User::get($mobile, true, User::PROMO);
    if (empty($user)) {
        JSON::fail('Incorrect mobile number.');
    }

    $log = Promo::getLastSMSLog($user);
    if (empty($log)) {
        JSON::fail('Incorrect sms code.');
    }

    $data = $log->getData();

    if ($data['code'] !== $code || time() - $data['createtime'] > $config['sms']['expired']) {
        JSON::fail('Invalid verification code or expired.');
    }

    $device = Device::get($data['device'], true);
    if (empty($device)) {
        JSON::fail('Device not exists.');
    }

    if ($num > $config['goods']['max']) {
        JSON::fail('number of goods exceeded.');
    }

    //获取第一货道上的商品，如果该商品数量不足，则去获取其它货道上的相同商品
    $goods = $device->getGoodsByLane(0);
    if ($goods && $goods['num'] < 1) {
        $goods = $device->getGoods($goods['id']);
    }

    if (empty($goods) || $goods['num'] < 1) {
        JSON::fail('Insufficient quantity of goods.');
    }

    $nonce_str = sha1("{$log->getId()}");
    $order_no = Order::makeUID($user, $device, $nonce_str);

    $order_data = [
        'src' => Order::FREE,
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'name' => $goods['name'],
        'goods_id' => $goods['id'],
        'num' => $num,
        'price' => 0,
        'ip' => Util::getClientIp(),
        'extra' => [
            'level' => LOG_GOODS_FREE,
            'goods' => $goods,
            'device' => [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ],
            'user' => $user->profile(),
            'promo' => [
                'log' => $log->getId(),
            ],
        ],
    ];

    $order = Order::create($order_data);

    if (empty($order)) {
        JSON::fail('An error occurred, please try again later!');
    }

    if (!Job::createOrderFor($order)) {
        JSON::fail('An error occurred, please try again later!');
    }

    JSON::success(['msg' => 'succeed, please wait for a moment.']);
}