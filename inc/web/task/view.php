<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Account;
use zovye\domain\Task;
use zovye\domain\User;
use zovye\model\task_vwModelObj;

$tpl_data = [];

$query = Task::query();

$account_id = Request::int('account');
if ($account_id > 0) {
    $account = Account::get($account_id);
    if ($account) {
        $tpl_data['account'] = $account->format();
    }
    $query->where(['account_id' => $account_id]);
}

if (Request::has('user')) {
    $user_id = Request::int('user');
    $user = User::get($user_id);
    if ($user) {
        $tpl_data['user'] = $user->profile();
    }
    $query->where(['user_id' => $user_id]);
}

if (Request::isset('status')) {
    $status = Request::int('status');
    $query->where(['s1' => $status]);
    $tpl_data['s_status'] = $status;
}

$total = $query->count();

if ($total > 0) {
    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', 10);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    $list = [];
    /** @var task_vwModelObj $entry */
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

Response::showTemplate('web/account/task_default', $tpl_data);