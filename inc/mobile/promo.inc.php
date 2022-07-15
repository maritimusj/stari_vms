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
    
        $res = Promo::veriyfSMS($user);
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
            'device' => $device->getImei(),
            'createtime' => time(),
        ])) {
            throw new RuntimeException('An error occurred, please try again later!');
        }
        
        JSON::success(['token' => $token, 'code' => $code]); //code here for debug

    } catch(RuntimeException $e) {
        JSON::fail($e);
    }

    
} elseif ($op == 'order') {
    
    $mobile = request::str('mobile');
    $code = request::str('code');
    $num = request::int('num', 1);

    if (empty($mobile) || empty($code)) {
        JSON::fail('invalid request params.');
    }

    $user = User::get($mobile, true, User::PROMO);
    if (empty($user)) {
        JSON::fail('incorrect mobile number.');
    }

    $log = Promo::getLastSMSLog($user);
    if (empty($log)) {
        JSON::fail('incorrect sms code.');
    }

    $data = $log->getData();
    
    //5分钟超时
    if ($data['code'] !== $code || time() - $data['createtime'] > 60 * 5) {
        JSON::fail('invlaid verification code or expired.');
    }

    $device = Device::get($data['device'], true);
    if (empty($device)) {
        JSON::fail('device not exists.');
    }


    

}