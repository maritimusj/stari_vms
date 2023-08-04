<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\withdraw;

defined('IN_IA') or exit('Access Denied');

use zovye\Config;
use zovye\CtrlServ;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use zovye\User;
use zovye\Wx;

$op = Request::op('default');

$log = [
    'id' => Request::int('id'),
];

if ($op == 'withdraw' && CtrlServ::checkJobSign($log)) {

    $user = User::get($log['id']);
    if (empty($user) || $user->isBanned()) {
        throw new JobException('找不到这个用户或者用户已禁用！', $log);
    }

    $tpl_id = Config::WxPushMessage('config.sys.tpl_id');
    if (empty($tpl_id)) {
        throw new JobException('没有配置模板消息id！', $log);
    }

    $admin_id = Config::WxPushMessage('config.sys.withdraw.user.id', 0);
    if (empty($admin_id)) {
        throw new JobException('没有指定广告审核管理员！', $log);
    }

    $admin = User::get($admin_id);
    if (empty($admin)) {
        throw new JobException('找不到指定广告审核管理员！', $log);
    }

    $log['data'] = [
        'thing9' => ['value' => '代理商提现审核'],
        'phrase25' => ['value' => '待审核'],
        'thing7' => ['value' => Wx::trim_thing($user->getName())],
        'phone_number28' => ['value' => $user->getMobile()],
        'time3' => ['value' => date('Y-m-d H:i:s')],
    ];

    $log['result'] = Wx::sendTemplateMsg([
        'touser' => $admin->getOpenid(),
        'template_id' => $tpl_id,
        'data' => $log['data'],
    ]);
}

Log::debug('withdraw', $log);