<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

//分组表
use zovye\business\ChargingServ;
use zovye\domain\Agent;
use zovye\domain\Device;
use zovye\domain\Group;
use zovye\model\device_groupsModelObj;

$query = Group::query(Group::CHARGING);

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

if (Request::isset('agent_id')) {
    $agent_id = Request::int('agent_id');
    if ($agent_id > 0) {
        $agent = Agent::get($agent_id);
        if (empty($agent)) {
            Response::toast('找不到这个代理商！', '', 'error');
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
/** @var device_groupsModelObj $group */
foreach ($query->findAll() as $group) {
    $data = $group->format();
    $agent = $group->getAgent();
    if ($agent) {
        $data['agent'] = $agent->profile();
    }

    $data['total'] = Device::query(['group_id' => $group->getId()])->count();
    $data['remote_version'] = ChargingServ::getGroupVersion($data['name']);

    $list[] = $data;
}

$tpl_data['list'] = $list;
$tpl_data['pager'] = We7::pagination($total, $page, $page_size);
$tpl_data['agentId'] = $agent_id ?? null;

Response::showTemplate('web/charging/default', $tpl_data);