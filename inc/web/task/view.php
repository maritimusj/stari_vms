<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\task_viewModelObj;

$tpl_data = [];

$query = Task::query();

$account_id = request::int('account');
if ($account_id > 0) {
    $account = Account::get($account_id);
    if ($account) {
        $tpl_data['account'] = $account->format();
    }
    $query->where(['account_id' => $account_id]);
}

if (request::has('user')) {
    $user_id = request::int('user');
    $user = User::get($user_id);
    if ($user) {
        $tpl_data['user'] = $user->profile();
    }
    $query->where(['user_id' => $user_id]);
}

if (request::isset('status')) {
    $status = request::int('status');
    $query->where(['s1' => $status]);
    $tpl_data['s_status'] = $status;
}

$total = $query->count();

if ($total > 0) {
    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $list = [];
    /** @var task_viewModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = $entry->format();
        $data['id'] = $entry->getId();
        if ($data['status'] == Task::INIT) {
            $data['status_formatted'] = '待审核';
        } elseif ($data['status'] == Task::REJECT) {
            $data['status_formatted'] = '已拒绝';
        } elseif ($data['status'] == Task::ACCEPT) {
            $data['status_formatted'] = '已通过';
        }
        $user = $entry->getUser();
        if ($user) {
            $data['user'] = $user->profile();
        }
        $data['submit'] = [
            'images' => $entry->getExtraData('data.images', []),
        ];
        $list[] = $data;
    }
    $tpl_data['list'] = $list;
    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
}

app()->showTemplate('web/account/task_default', $tpl_data);