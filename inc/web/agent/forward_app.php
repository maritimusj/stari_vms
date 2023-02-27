<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tp_lid = settings('notice.agentReq_tplid');
if (empty($tp_lid)) {
    JSON::fail('请先设置消息模板ID，否则无法推送通知!');
}

$agent_ids = request('agentids');
$id = request::int('id');

if (empty($agent_ids) || empty($id)) {
    JSON::fail('请求参数不正确！');
}

$app = m('agent_app')->findOne(We7::uniacid(['id' => $id]));
if ($app) {
    if (Job::agentAppForward($app->getId(), $agent_ids)) {
        JSON::success('已提交请求到控制中心！');
    }
}

JSON::fail('转发请求处理失败！');