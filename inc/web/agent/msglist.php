<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\agent_msgModelObj;

$id = Request::int('id');
$user = User::get($id);
if (empty($user) || !($user->isAgent() || $user->isPartner())) {
    Util::itoast('用户不是代理商或者代理商合伙人！', We7::referer(), 'error');
}

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 10);

$query = m('agent_msg')->where(We7::uniacid(['agent_id' => $id]));

$total = $query->count();

$messages = [];

if ($total > 0) {
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

$tpl_data[] = [
    'op' => 'msglist',
    'id' => $id,
    'user' => $user,
    'messages' => $messages,
];

$from = Request::str('from');
if ($from == 'agent') {
    $tpl_data['back_url'] = $this->createWebUrl('agent', ['id' => $id]);
} elseif ($from == 'partner') {
    $tpl_data['back_url'] = $this->createWebUrl('agent', ['op' => 'partner', 'id' => $user->getAgentId()]);
}

app()->showTemplate('web/agent/agent_msg', $tpl_data);