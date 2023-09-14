<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Agent;
use zovye\domain\User;
use zovye\util\DBUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$from = Request::str('from') ?: 'partnerAdd';

$agent_id = Request::int('agentid');
$user_id = Request::int('userid');

$agent = Agent::get($agent_id);
if (empty($agent)) {
    Response::toast('找不到这个代理商！', Util::url('agent'), 'error');
}

$user = User::get($user_id);
if (empty($user)) {
    Response::toast('找不到这个用户！', Util::url('agent', ['op' => 'partner', 'agentid' => $agent_id]), 'error');
}

$name = Request::trim('name');
$mobile = Request::trim('mobile');

$notice = [
    'device' => [
        'online' => Request::bool('deviceOnline') ? 1 : 0,
        'offline' => Request::bool('deviceOffline') ? 1 : 0,
        'error' => Request::bool('deviceError') ? 1 : 0,
        'low_battery' => Request::bool('deviceLowBattery') ? 1 : 0,
        'low_remain' => Request::bool('deviceLowRemain') ? 1 : 0,
    ],
    'order' =>  [
        'succeed' => Request::bool('orderSucceed') ? 1 : 0,
        'failed' => Request::bool('orderFailed') ? 1 : 0,
    ]
];

if (empty($mobile)) {
    Response::toast(
        '请填写合伙人的手机号码！',
        Util::url('agent', ['op' => $from, 'agentid' => $agent_id, 'userid' => $user_id]),
        'error'
    );
}

$res = User::findOne(['id <>' => $user_id, 'mobile' => $mobile, 'app' => User::WX]);
if ($res) {
    Response::toast(
        '手机号码已经被其它用户使用！',
        Util::url('agent', ['op' => $from, 'agentid' => $agent_id, 'userid' => $user_id]),
        'error'
    );
}

$res = DBUtil::transactionDo(
    function () use ($agent, $user, $name, $mobile, $notice) {
        if ($agent->setPartner($user, $name, $mobile, $notice)) {
            return true;
        }
        return err('设置失败！');
    }
);

$url = Util::url('agent', ['op' => 'partner_edit', 'agentid' => $agent_id, 'userid' => $user->getId()]);

if (is_error($res)) {
    Response::toast('合伙人保存失败！', $url, 'error');
}

Response::toast('合伙人保存成功！', $url, 'success');