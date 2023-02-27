<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\agent_msgModelObj;

$id = request::int('id');

/** @var agent_msgModelObj $msg */
$msg = m('agent_msg')->findOne(We7::uniacid(['id' => $id]));

if ($msg) {
    $agent_id = $msg->getAgentId();
    $user = User::get($agent_id);
    $from = $user->isAgent() ? 'agent' : 'partner';

    $msg->destroy();
    Util::itoast(
        '删除成功！',
        $this->createWebUrl('agent', ['op' => 'msglist', 'id' => $agent_id, 'from' => $from]),
        'success'
    );
}

Util::itoast('删除失败！', We7::referer(), 'error');