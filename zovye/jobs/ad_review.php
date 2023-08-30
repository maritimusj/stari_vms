<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\adReview;

defined('IN_IA') or exit('Access Denied');

//广告审核

use zovye\Advertising;
use zovye\App;
use zovye\Config;
use zovye\CtrlServ;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use zovye\ReviewResult;
use zovye\User;
use zovye\Util;
use zovye\Wx;

$log = [
    'id' => Request::int('id'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$tpl_id = Config::WxPushMessage('config.sys.tpl_id');
if (empty($tpl_id)) {
    throw new JobException('没有配置模板消息id！', $log);
}

$user_id = Config::WxPushMessage('config.sys.review.user.id', 0);
if (empty($user_id)) {
    throw new JobException('没有指定广告审核管理员！', $log);
}

$user = User::get($user_id);
if (empty($user)) {
    throw new JobException('找不到指定广告审核管理员！', $log);
}

$ad = Advertising::get($log['id']);
if (empty($ad)) {
    throw new JobException('找不到这个广告！', $log);
}

if ($ad->getReviewResult() != ReviewResult::WAIT) {
    throw new JobException('这个广告已审核！', $log);
}

$agent = $ad->getAgent();
if (empty($agent)) {
    throw new JobException('找不到广告所属代理商！', $log);
}

$url = Util::murl(
    'util',
    [
        'op' => 'adv_review',
        'id' => $ad->getId(),
        'sign' => sha1(App::uid()."{$user->getId()}:{$ad->getId()}"),
    ]
);

$log['data'] = [
    'thing9' => ['value' => '广告审核'],
    'phrase25' => ['value' => '待审核'],
    'thing7' => ['value' => Wx::trim_thing($agent->getName())],
    'phone_number28' => ['value' => $agent->getMobile()],
    'time3' => ['value' => date('Y-m-d H:i:s')],
];

$log['result'] = Wx::sendTemplateMsg([
    'touser' => $user->getOpenid(),
    'template_id' => $tpl_id,
    'url' => $url,
    'data' => $log['data'],
]);

Log::debug('adv_review', $log);
