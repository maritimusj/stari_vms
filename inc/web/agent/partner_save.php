<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$from = Request::str('from') ?: 'partnerAdd';

$agent_id = Request::int('agentid');
$user_id = Request::int('userid');

$agent = Agent::get($agent_id);
if (empty($agent)) {
    Response::toast('找不到这个代理商！', $this->createWebUrl('agent'), 'error');
}

$user = User::get($user_id);
if (empty($user)) {
    Response::toast('找不到这个用户！', $this->createWebUrl('agent', ['op' => 'partner', 'agentid' => $agent_id]), 'error');
}

$name = Request::trim('name');
$mobile = Request::trim('mobile');

$notice = [
    'agentApp' => request('agentApp') ? 1 : 0,
    'order' => request('orderNotify') ? 1 : 0,
    'remainWarning' => request('remainWarning') ? 1 : 0,
    'deviceError' => request('deviceError') ? 1 : 0,
    'reviewResult' => request('reviewResult') ? 1 : 0,
    'agentMsg' => request('agentMsg') ? 1 : 0,
];

if (empty($mobile)) {
    Response::toast(
        '请填写合伙人的手机号码！',
        $this->createWebUrl('agent', ['op' => $from, 'agentid' => $agent_id, 'userid' => $user_id]),
        'error'
    );
}

$res = User::findOne(['id <>' => $user_id, 'mobile' => $mobile, 'app' => User::WX]);
if ($res) {
    Response::toast(
        '手机号码已经被其它用户使用！',
        $this->createWebUrl('agent', ['op' => $from, 'agentid' => $agent_id, 'userid' => $user_id]),
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

if (is_error($res)) {
    Response::toast('合伙人保存失败！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

Response::toast('合伙人保存成功！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'success');