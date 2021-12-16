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
        $result['participated'][] = $entry->format();
        //过滤掉已参与未完成的任务
        foreach ($result['new'] as $index => $item) {
            if ($entry->getAccountId() == $item['id']) {
                unset($result['new'][$index]);
            }
        }
    }

    JSON::success($result);

} elseif ($op == 'detail') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $account = Account::findOneFromUID(request::str('uid'));
    if (empty($account)) {
        JSON::fail('任务不存在！');
    }

    $data = $account->format();

    $task = Task::findOne([
        'user_id' => $user->getId(),
        'account_id' => $account->getId(),
        's1' => [Task::INIT, Task::REJECT],
    ]);

    if ($task) {
        $data['status'] = $task->getState();
    }

    $data['detail'] = [
        [
            'title' => '任务说明',
            'desc' => html_entity_decode($account->getConfig('desc', '')),
        ],
        [
            'title' => '完成条件',
            'text' => '上传截图',
        ],
        [
            'title' => '图片',
            'img' => [
                Util::toMedia('images/2/2021/11/bUiuE0Zq30AE47eaIInQliIE4qE0i3.jpg'),
                Util::toMedia('images/2/2021/11/bUiuE0Zq30AE47eaIInQliIE4qE0i3.jpg')
            ],
        ],
        [
            'title' => '二维码',
            'qrcode' => Util::toMedia('images/2/2021/11/bUiuE0Zq30AE47eaIInQliIE4qE0i3.jpg'),
        ],
        [
            'title' => '任务链接',
            'url' => 'http://www.baidu.com',
        ],
    ];

    JSON::success($data);

} elseif ($op == 'submit') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $uid = request::str('uid');
    $account = Account::findOneFromUID($uid);
    if (empty($account)) {
        JSON::fail('找不到这个任务！');
    }

    if ($account->getBonusType() != Account::BALANCE || $account->getBalancePrice() == 0) {
        JSON::fail('任务未设置积分奖励！');
    }

    $data = request::array('data');
    if (empty($data)) {
        JSON::fail('提交的数据为空！');
    }

    if (!$user->acquireLocker(User::TASK_LOCKER)) {
        JSON::fail('用户无法锁定，请重试！');
    }

    $data['time'] = time();

    $result = Util::transactionDo(function () use ($user, $account, $data) {
        $exists_task = Task::findOne([
            'user_id' => $user->getId(),
            'account_id' => $account->getId(),
            's1' => [Task::INIT, Task::REJECT],
        ]);

        if ($exists_task) {
            $exists_task->setS1(Task::INIT);
            $exists_task->updateSettings('extra', $data);
            if ($exists_task->save()) {
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