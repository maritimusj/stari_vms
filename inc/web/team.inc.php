<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\team_memberModelObj;

$op = request::op('default');

if ($op == 'detail') {
    
    $user_id = request::int('id');
    $user = User::get($user_id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', $this->createWebUrl('user'), 'error');
    }

    $team = Team::getOrCreateFor($user);
    if (empty($team)) {
        Util::itoast('找不车队或者创建车队失败！', $this->createWebUrl('user'), 'error');
    }

    $tpl_data = [];

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query = Team::findAllMember($team);
    $total = $query->count();

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);

    $list = [];

    /** @var team_memberModelObj $member */
    foreach ($query->findAll() as $member) {
        $data = $member->profile();
        $user = $member->user();
        if ($user) {
            $data['balance'] = $user->getCommissionBalance()->total();
        }
        $list[] = $data;
    }

    $tpl_data['list'] = $list;
    app()->showTemplate('web/team/default', $tpl_data);

} elseif ($op == 'editCredit') {
    $member_id = request::int('id');

    $member = Team::getMember($member_id);
    if (empty($member)) {
        JSON::fail('找不到这个车队成员！');
    }

    $user = $member->user();
    if (empty($user)) {
        JSON::fail('找不到这个车队成员关联的用户！');
    }

    $content = app()->fetchTemplate(
        'web/user/credit',
        [
            'user' => $user->profile(),
            'val' => $user->getCredit(),
        ]
    );

    JSON::success(['title' => '透支额度', 'content' => $content]);

} elseif ($op == 'saveCredit') {
    $user_id = request::int('id');
    $user = User::get($user_id);

    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $val = request::int('val');

    if ($user->setCredit($val)) {
        JSON::success('已保存！');
    }

    JSON::fail('保存失败！');
}