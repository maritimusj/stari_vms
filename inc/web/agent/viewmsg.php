<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\agent_msgModelObj;

$agent_id = request::isset('id') ? request::int('id') : request::int('agentid');
$agent = User::get($agent_id);
if (empty($agent) || !($agent->isAgent() || $agent->isPartner())) {
    JSON::fail('找不到这个代理商！');
}

$page = max(1, request::int('page'));
$page_size = request::int('pagesize', 10);
$pager = '';

$query = m('agent_msg')->where(We7::uniacid(['agent_id' => $agent->getId()]));

$total = $query->count();
$messages = [];

if ($total > 0) {
    $pager = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    /** @var agent_msgModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $messages[] = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'updatetime' => $entry->getUpdatetime(),
        ];
    }
}

$content = app()->fetchTemplate(
    'web/agent/agent_msg',
    [
        'agent' => $agent,
        'message' => $messages,
        'pager' => $pager,
    ]
);

JSON::success(['title' => "<b>{$agent->getName()}</b>的消息", 'content' => $content]);