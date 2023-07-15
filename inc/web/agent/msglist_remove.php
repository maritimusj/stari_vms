<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\agent_msgModelObj;

$id = Request::int('id');

/** @var agent_msgModelObj $msg */
$msg = m('agent_msg')->findOne(We7::uniacid(['id' => $id]));

if ($msg) {
    $agent_id = $msg->getAgentId();
    $user = User::get($agent_id);
    $from = $user->isAgent() ? 'agent' : 'partner';

    $msg->destroy();

    Response::toast(
        '删除成功！',
        $this->createWebUrl('agent', ['op' => 'msglist', 'id' => $agent_id, 'from' => $from]),
        'success'
    );
}

Response::toast('删除失败！', We7::referer(), 'error');