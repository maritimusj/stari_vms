<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\douyin;

defined('IN_IA') or exit('Access Denied');

use zovye\business\DouYin;
use zovye\CtrlServ;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use function zovye\is_error;

$log = [
    'id' => Request::int('id'),
    'device' => Request::int('device'),
    'uid' => Request::str('uid'),
    'time' => Request::int('time'),
];

$writeLog = function () use (&$log) {
    Log::debug('douyin_order', $log);
};

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

if (!Locker::try("douyin:{$log['id']}:{$log['uid']}")) {
    $log['error'] = '锁定用户失败！';
    Job::exit($writeLog);
}

$user = User::get($log['id']);
if (empty($user)) {
    $log['error'] = '找不到这个用户！';
    Job::exit($writeLog);
}

$device = Device::get($log['device']);
if (empty($device)) {
    $log['error'] = '找不到这个设备！';
    Job::exit($writeLog);
}

$account = Account::findOneFromUID($log['uid']);
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

    if (time() - $log['time'] > 60) {
        $log['error'] = '用户操作时间已超过60秒！';
        Job::exit($writeLog);
    }
}

$log['restart'] = Job::douyinOrder($user, $device, $log['uid'], $log['time']);

Job::exit($writeLog);
