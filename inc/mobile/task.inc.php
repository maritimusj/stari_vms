<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\task_viewModelObj;

$op = request::op('default');

if ($op == 'default') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $device_shadow_id = request::str('device');
    if ($device_shadow_id) {
        $device = Device::findOne(['shadow_id' => $device_shadow_id]);
    }

    app()->taskPage($user, $device ?? null);
} elseif ($op == 'get_list') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $max = request::int('max', 10);

    $result = [];

    $result['new'] = Account::getAvailableList(Device::getBalanceVDevice(), $user, [
        'type' => [Account::TASK],
        's_type' => [],
        'include' => [Account::BALANCE],
        'max' => $max,
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

    JSON::success($result);
} elseif ($op == 'detail') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $uid = request::str('uid');
    $account = Account::findOneFromUID($uid);
    if ($account) {
        $data = $account->format();
    } else {
        $task = Task::get($uid, true);
        if (empty($task)) {
            JSON::fail('任务不存在！');
        }
        $account = $task->getAccount();
        if (empty($task)) {
            JSON::fail('任务不存在！');
        }

        $data = $task->format();
        $data['uid'] = $task->getUid();
    }

    $data['detail'] = [];

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

    $desc = html_entity_decode($account->getConfig('desc', ''));
    $data['detail'][] = [
        'title' => '任务说明',
        'desc' => $desc,
    ];

    if (isset($task)) {
        $item = [
            'title' => '已提交的任务图片',
            'img' => [],
        ];
        $images = $task->settings('extra.images', []);
        if ($images) {
            foreach ($images as $img) {
                $item['img'][] = Util::toMedia($img, true);
            }
            $data['detail'][] = $item;            
        }
    }

    JSON::success($data);
} elseif ($op == 'submit') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if (!$user->acquireLocker(User::TASK_LOCKER)) {
        return err('用户无法锁定，请重试！');
    }

    $result = Util::transactionDo(function () use ($user) {

        $task = null;

        $uid = request::str('uid');
        $account = Account::findOneFromUID($uid);

        if (empty($account)) {
            $task = Task::get($uid, true);
            if (empty($task)) {
                return err('任务不存在！');
            }
            if ($task->getS1() != Task::INIT && $task->getS1() != Task::REJECT) {
                return err('任务已完成！');
            }
            $account = $task->getAccount();
        }

        if (empty($account)) {
            return err('任务不存在！');
        }

        if ($account->getBonusType() != Account::BALANCE) {
            return err('任务未设置积分奖励！');
        }

        $data = request::array('data');
        if (empty($data)) {
            return err('提交的数据为空！');
        }

        $data['time'] = time();

        if ($task) {
            $task->setS1(Task::INIT);
            $task->updateSettings('extra', $data);
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

    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('提交成功！');
}