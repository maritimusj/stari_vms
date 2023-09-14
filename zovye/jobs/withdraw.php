<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\withdraw;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\domain\User;
use zovye\Helper;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use zovye\Wx;

$log = [
    'id' => Request::int('id'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!');
}

$user = User::get($log['id']);
if (empty($user) || $user->isBanned()) {
    throw new JobException('找不到这个用户或者用户已禁用！', $log);
}

$log['data'] = [
    'thing9' => ['value' => '提现申请'],
    'phrase25' => ['value' => '待审核'],
    'thing7' => ['value' => Wx::trim_thing($user->getName())],
    'phone_number28' => ['value' => $user->getMobile()],
    'time3' => ['value' => date('Y-m-d H:i:s')],
];

$log['result'] = Helper::sendSysTemplateMessageTo('withdraw', $log['data']);

Log::debug('withdraw', $log);