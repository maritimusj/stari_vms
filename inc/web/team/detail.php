<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\team_memberModelObj;

$user_id = Request::int('id');
$user = User::get($user_id);
if (empty($user)) {
    Util::itoast('找不到这个用户！', $this->createWebUrl('user'), 'error');
}

$team = Team::getOrCreateFor($user);
if (empty($team)) {
    Util::itoast('找不车队或者创建车队失败！', $this->createWebUrl('user'), 'error');
}

$tpl_data = [];

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Team::findAllMember($team);
$total = $query->count();

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);

$list = [];

/** @var team_memberModelObj $member */
foreach ($query->findAll() as $member) {
    $data = $member->profile();
    $user = $member->getAssociatedUser();
    if ($user) {
        $data['balance'] = $user->getCommissionBalance()->total();
        $data['credit'] = $user->getCredit();
        $data['dynamic'] = empty($member->user());
    }
    $list[] = $data;
}

$tpl_data['list'] = $list;
app()->showTemplate('web/team/default', $tpl_data);
