<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Balance;
use zovye\domain\Task;
use zovye\domain\User;
use zovye\util\DBUtil;

defined('IN_IA') or exit('Access Denied');

$result = DBUtil::transactionDo(function () {
    $task = Task::get(Request::int('id'));

    if (empty($task)) {
        return err('找不到这个记录！');
    }

    if ($task->getS1() != Task::INIT) {
        return err('这个记录不需要审核！');
    }

    $way = Request::str('way');
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