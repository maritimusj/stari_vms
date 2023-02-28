<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

//新分组管理
use zovye\model\device_groupsModelObj;

$tpl_data = [];

//分组表
$query = Group::query(Group::NORMAL);

$page = max(1, request::int('page'));
$page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

if (request::isset('agent_id')) {
    $agent_id = request::int('agent_id');
    if ($agent_id > 0) {
        $agent = Agent::get($agent_id);
        if (empty($agent)) {
            Util::itoast('找不到这个代理商！', '', 'error');
        }
        $query->where(['agent_id' => $agent_id]);
    } else {
        $query->where(['agent_id' => 0]);
    }
}

$total = $query->count();

//列表数据
$query->page($page, $page_size);

$list = [];
/** @var device_groupsModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'clr' => $entry->getClr(),
        'total' => Device::query(['group_id' => $entry->getId()])->count(),
        'createtime_formatted' => date('Y-m-d H:i', $entry->getCreatetime()),
    ];
    $agent = $entry->getAgent();
    if ($agent) {
        $data['agent'] = $agent->profile();
    }
    $list[] = $data;
}

$filter = [
    'page' => $page,
    'pagesize' => $page_size,
];

$tpl_data['groups'] = $list;
$tpl_data['filter'] = $filter;
$tpl_data['pager'] = We7::pagination($total, $page, $page_size);
$tpl_data['agentId'] = $agent_id ?? null;

app()->showTemplate('web/device/new_group', $tpl_data);