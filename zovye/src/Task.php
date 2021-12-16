<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\accountModelObj;
use zovye\model\balance_logsModelObj;
use zovye\model\task_viewModelObj;
use zovye\model\userModelObj;

class Task
{
    const INIT = 0;
    const REJECT = 1;
    const ACCEPT = 2;

    public static function createLog(userModelObj $user, accountModelObj $account, array $data): ?task_viewModelObj
    {
        $log = BalanceLog::create([
            'user_id' => $user->getId(),
            'account_id' => $account->getId(),
            's1' => self::INIT,
            's2' => sha1("{$user->getId()}{$account->getId()}" . Util::random(16)),
            'extra' => [
                'data' => $data,
            ],
        ]);

        if (!$log) {
            return null;
        }

        $taskLog = new task_viewModelObj($log->getId(), $log->factory());
        $data = $log->__getData('all');
        $taskLog->__setData($data);

        return $taskLog;
    }

    public static function query($condition = [])
    {
        return m('task_view')->query($condition);
    }

    public static function get($id, bool $is_uid = false): ?task_viewModelObj
    {
        if ($is_uid) {
            return self::findOne(['s2' => $id]);
        }
        return self::findOne(['id' => $id]);
    }

    public static function findOne($condition = []): ?task_viewModelObj
    {
        return self::query($condition)->findOne();
    }
}