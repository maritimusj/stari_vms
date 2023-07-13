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
            's2' => sha1("{$user->getId()}{$account->getId()}".Util::random(16)),
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

    public static function query($condition = []): base\modelObjFinder
    {
        return m('task_vw')->query($condition);
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

    public static function brief(accountModelObj $account)
    {
        $res = self::query(['account_id' => $account->getId()])->groupBy('s1')->getAll(['s1', 'count(*) AS n']);
        if (empty($res)) {
            return [];
        }
        $result = [];
        foreach ($res as $item) {
            $result[$item['s1']] = $item['n'];
        }

        return $result;
    }

    public static function getList(userModelObj $user, int $limit = 0): array
    {
        $result = [];

        $result['new'] = Account::getAvailableList(Device::getDummyDevice(), $user, [
            'type' => [Account::TASK],
            's_type' => [],
            'include' => [Account::BALANCE],
            'max' => $limit,
        ]);

        $query = Task::query([
            'user_id' => $user->getId(),
            's1' => [
                Task::INIT,
                Task::REJECT,
            ],
            'state' => Account::NORMAL,
        ]);

        $query->orderBy('id DESC');

        $result['participated'] = [];
        /** @var task_viewModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = $entry->format();
            $data['uid'] = $entry->getUid();
            $result['participated'][] = $data;
            //过滤掉已参与未完成的任务
            foreach ($result['new'] as $index => $item) {
                if ($entry->getAccountId() == $item['id']) {
                    unset($result['new'][$index]);
                }
            }
        }

        $result['new'] = array_values($result['new']);
        $result['participated'] = array_values($result['participated']);

        return $result;
    }

    public static function detail($uid): array
    {
        $account = $uid instanceof accountModelObj ? $uid : Account::findOneFromUID(strval($uid));
        if ($account) {
            $data = $account->format();
        } else {
            $task = Task::get(strval($uid), true);
            if (empty($task)) {
                return err('任务不存在！');
            }
            $account = $task->getAccount();
            if (empty($account)) {
                return err('任务不存在！');
            }

            $data = $task->format();
            $data['uid'] = $task->getUid();
        }

        $data['detail'] = [];

        $desc = html_entity_decode($account->getConfig('desc', ''));
        $data['detail'][] = [
            'title' => '任务说明',
            'desc' => $desc,
        ];

        $url = $account->getConfig('url', '');
        if ($url) {
            $data['detail'][] = [
                'title' => '任务链接',
                'url' => $url,
            ];
        }

        $qrcode = $account->getConfig('qrcode', '');
        if ($qrcode) {
            $data['detail'][] = [
                'title' => '二维码',
                'qrcode' => Util::toMedia($qrcode),
            ];
        }

        $images = $account->getConfig('images', []);
        if ($images) {
            $item = [
                'title' => '截图示例',
                'img' => [],
            ];
            foreach ($images as $img) {
                $item['img'][] = Util::toMedia($img, true);
            }
            $data['detail'][] = $item;
        }

        if (isset($task)) {
            $item = [
                'title' => '我提交的截图',
                'img' => [],
            ];
            $images = $task->getExtraData('data.images', []);
            if ($images) {
                foreach ($images as $img) {
                    $item['img'][] = Util::toMedia($img, true);
                }
                $data['detail'][] = $item;
            }
        }

        return $data;
    }

    public static function submit($uid, array $data, userModelObj $user)
    {
        $account = $uid instanceof accountModelObj ? $uid : Account::findOneFromUID(strval($uid));

        return DBUtil::transactionDo(function () use ($uid, $data, $user, $account) {

            $task = null;
            if (empty($account)) {
                $task = Task::get(strval($uid), true);
                if (empty($task)) {
                    return err('任务不存在！');
                }
                if ($task->getS1() == Task::ACCEPT) {
                    return err('任务已完成！');
                }
                if (!in_array($task->getS1(), [Task::INIT, Task::REJECT])) {
                    return err('任务状态异常！');
                }
                $account = $task->getAccount();
            }

            if (empty($account)) {
                return err('任务不存在！');
            }

            if ($account->getBonusType() != Account::BALANCE) {
                return err('任务未设置积分奖励！');
            }

            $data['time'] = time();

            if ($task) {
                $task->setS1(Task::INIT);
                $task->setExtraData('data', $data);
                if ($task->save()) {
                    return true;
                }
            } else {
                if (Task::createLog($user, $account, $data)) {
                    return true;
                }
            }

            return err('保存记录失败！');
        });
    }
}