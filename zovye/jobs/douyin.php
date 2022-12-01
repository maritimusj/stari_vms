<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\douyin;

use zovye\Account;
use zovye\CtrlServ;
use zovye\Device;
use zovye\DouYin;
use zovye\Job;
use zovye\Locker;
use zovye\Log;
use zovye\Order;
use zovye\request;
use zovye\User;
use function zovye\is_error;

$op = request::op('default');
$data = [
    'id' => request::int('id'),
    'device' => request::int('device'),
    'uid' => request::str('uid'),
    'time' => request::int('time'),
];

$log = [
    'data' => $data,
];

$writeLog = function () use (&$log) {
    Log::debug('douyin_order', $log);
};

if ($op == 'douyin' && CtrlServ::checkJobSign($data)) {

    if (!Locker::try("douyin:{$data['id']}:{$data['uid']}")) {
        $log['error'] = '锁定用户失败！';
        Job::exit($writeLog);
    }

    $user = User::get($data['id']);
    if (empty($user)) {
        $log['error'] = '找不到这个用户！';
        Job::exit($writeLog);
    }

    $device = Device::get($data['device']);
    if (empty($device)) {
        $log['error'] = '找不到这个设备！';
        Job::exit($writeLog);
    }

    $account = Account::findOneFromUID($data['uid']);
    if (empty($account)) {
        $log['error'] = '找不到这个公众号！';
        Job::exit($writeLog);
    }

    $openid = $account->settings('config.openid', '');
    if (empty($openid)) {
        $log['error'] = '没有指定关注账号的openid';
    }

    for ($i = 0; $i < 10; $i++) {
        //延时一定时间后读取用户关注列表
        usleep((2 + log($i + 1, 2)) * 1000000);

        $result = DouYin::isFans($user, $openid);
        if (is_error($result)) {
            $log['error'] = $result;
            Job::exit($writeLog);
        }

        if ($result) {
            $log['order'] = Job::createAccountOrder([
                'device' => $device->getId(),
                'user' => $user->getId(),
                'account' => $account->getId(),
                'orderUID' => Order::makeUID($user, $device, sha1("douyin:".$account->getUid())),
            ]);
            Job::exit($writeLog);
        }

        if (time() - $data['time'] > 60) {
            $log['error'] = '用户操作时间已超过60秒！';
            Job::exit($writeLog);
        }
    }
    $log['restart'] = Job::douyinOrder($user, $device, $data['uid'], $data['time']);
} else {
    $log['error'] = '签名校验失败！';
}

Job::exit($writeLog);
