<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tp_lid = settings('agent.msg_tplid');
if (empty($tp_lid)) {
    JSON::fail('请先设置消息模板ID，否则无法推送提醒消息!');
}

$agent_ids = request('agentids');
$id = request::int('id');

if (empty($agent_ids) || empty($id)) {
    JSON::fail('请求参数不正确！');
}

$msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
if ($msg && $msg->set('agents', $agent_ids)) {
    $res = Job::agentMsgNotice($msg->getId());
    if (!is_error($res)) {
        JSON::success('已发送请求到控制中心！');
    }
}

JSON::fail('请求发送提醒消息失败！');