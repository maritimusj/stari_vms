<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\task_viewModelObj;

$op = request::op('default');

if ($op == 'view') {

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

} elseif ($op == 'verify') {

    $result = Util::transactionDo(function () {
        $task = Task::get(request::int('id'));

        if (empty($task)) {
            return err('找不到这个记录！');
        }

        if ($task->getS1() != Task::INIT) {
            return err('这个记录不需要审核！');
        }

        $way = request::str('way');
        if ($way == 'reject') {
            $task->setS1(Task::REJECT);
            if ($task->save()) {
                return ['code' => Task::REJECT, 'title' => '已拒绝！'];
            }
        } elseif ($way == 'accept') {
            $task->setS1(Task::ACCEPT);

            $account = $task->getAccount();
            if (empty($account)) {
                return err('找不到这个任务！');
            }

            $user = $task->getUser();
            if (empty($user)) {
                return err('找不到用户！');
            }

            if (!$user->acquireLocker(User::TASK_LOCKER)) {
                return err('用户无法锁定，请重试！');
            }

            $result = Balance::give($user, $account);
            if (is_error($result)) {
                return $result;
            }

            if ($task->save()) {
                return ['code' => Task::ACCEPT, 'title' => '已通过！'];
            }
        }

        return err('操作失败！');
    });

    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success($result);
}