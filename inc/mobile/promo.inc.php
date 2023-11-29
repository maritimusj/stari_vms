<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;
use zovye\business\ChuanglanSmsApi;
use zovye\business\Promo;
use zovye\domain\Device;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\model\userModelObj;
use zovye\util\DBUtil;

$op = Request::op('default');
if ($op == 'sms') {
    if (!App::isSmsPromoEnabled()) {
        JSON::fail('没有启用这个功能！');
    }

    $result = DBUtil::transactionDo(function() {
        $mobile = Request::trim('mobile');

        if (empty($mobile)) {
            throw new RuntimeException('invalid mobile phone number.');
        }
    
        $device = Device::get(Request::str('device'), true);
        if (empty($device)) {
            throw new RuntimeException('fail to get device info.');
        }
    
        if (!$device->lockAcquire()) {
            throw new RuntimeException('an error occurred, please try again later.');
        }

        /** @var userModelObj $user */
        $user = User::findOne(['mobile' => $mobile]);
        if (empty($user)) {
            $user = User::getOrCreate($mobile, User::PROMO, [
                'nickname' => $mobile,
                'mobile' => $mobile,
                'avatar' => MODULE_URL . 'static/img/unknown.svg',
            ]);
        }

        if (empty($user)) {
            throw new RuntimeException('fail to get user info!');
        }
    
        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            throw new RuntimeException('an error occurred, please try again later.');
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
            throw new RuntimeException("fail to send sms: {$res['error']}");
        }

        if (!Promo::createSMSLog($user, [
            'code' => $code,
            'result' => $res,
            'device' => $device->getImei(),
            'createtime' => time(),
        ])) {
            throw new RuntimeException('an error occurred, please try again later.');
        }
        
        $config = Promo::getConfig();

        return ['delay' => $config['sms']['delay']];
    });

    JSON::result($result);

} elseif ($op == 'verify') {
    
    $mobile = Request::str('mobile');
    $code = Request::str('code');
    $num = Request::int('num', 1);

    $config = Promo::getConfig();

    if (empty($mobile) || empty($code)) {
        JSON::fail('invalid request params.');
    }

    $user = User::findOne(['mobile' => $mobile]);
    if (empty($user)) {
        $user = User::get($mobile, true, User::PROMO);
    }

    if (empty($user)) {
        JSON::fail('incorrect mobile number.');
    }

    if (!$user->acquireLocker(User::ORDER_LOCKER)) {
        throw new RuntimeException('an error occurred, please try again later.');
    }

    $log = Promo::getLastSMSLog($user);
    if (empty($log)) {
        JSON::fail('incorrect sms code.');
    }

    $data = $log->getData();

    if ($data['code'] !== $code || time() - $data['createtime'] > $config['sms']['expired']) {
        JSON::fail('invalid verification code or expired.');
    }

    if ($data['orderNO']) {
        JSON::fail('invalid verification code or expired.');
    }

    $device = Device::get($data['device'], true);
    if (empty($device)) {
        JSON::fail('device not exists.');
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
        JSON::fail('insufficient quantity of goods.');
    }

    $nonce_str = sha1("{$log->getId()}");
    $order_no = Order::makeUID($user, $device, $nonce_str);

    $order_data = [
        'src' => Order::FREE,
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'user_id' => $user->getId(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'name' => $goods['name'],
        'goods_id' => $goods['id'],
        'num' => $num,
        'price' => 0,
        'ip' => CLIENT_IP,
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
        JSON::fail('an error occurred, please try again later.');
    }

    if (!Job::createOrderFor($order)) {
        JSON::fail('an error occurred, please try again later.');
    }

    $log->setData('orderNO', $order_no);
    $log->save();

    JSON::success([
        'msg' => 'succeed, please wait for a moment.',
        'orderNO' => $order_no,
    ]);
}