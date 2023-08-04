<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\advReview;

defined('IN_IA') or exit('Access Denied');

//广告审核

use zovye\Advertising;
use zovye\App;
use zovye\Config;
use zovye\CtrlServ;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use zovye\User;
use zovye\Util;
use zovye\Wx;
use function zovye\err;

$op = Request::op('default');
$log = [
    'id' => Request::int('id'),
];

if ($op == 'adv_review' && CtrlServ::checkJobSign($log)) {
    $tpl_id = Config::WxPushMessage('config.sys.tpl_id');
    if (empty($tpl_id)) {
        throw new JobException('没有配置模板消息id！', $log);
    }

    $user_id = Config::WxPushMessage('config.sys.review.user.id', 0);
    if (empty($user_id)) {
        throw new JobException('没有指定代理审核管理员！', $log);
    }

    $user = User::get($user_id);
    if (empty($user)) {
        throw new JobException('找不到指定代理审核管理员！', $log);
    }

    $ad = Advertising::get($log['id']);
    if (empty($ad)) {
        throw new JobException('找不到这个广告！', $log);
    }

    $url = Util::murl(
        'util',
        [
            'op' => 'adv_review',
            'id' => $ad->getId(),
            'sign' => sha1(App::uid()."{$user->getId()}:{$ad->getId()}"),
        ]
    );

    $log['data'] = [];

    $log['result'] = Wx::sendTemplateMsg([
        'touser' => $user->getOpenid(),
        'template_id' => $tpl_id,
        'url' => $url,
        'data' => $log['data'],
    ]);
}

Log::debug('adv_review', $log);
