<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'default') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $device_shadow_id = Request::str('device');
    if ($device_shadow_id) {
        $device = Device::findOne(['shadow_id' => $device_shadow_id]);
    }

    app()->taskPage([
        'user' => $user,
        'device' => $device ?? null,
    ]);

} elseif ($op == 'get_list') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $max = Request::int('max', 10);

    $result = Task::getList($user, $max);

    JSON::success($result);

} elseif ($op == 'detail') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $uid = Request::str('uid');

    $res = Task::detail($uid);
    if (is_error($res)) {
        JSON::fail($res);
    }

    JSON::success($res);

} elseif ($op == 'submit') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if (!$user->acquireLocker(User::TASK_LOCKER)) {
        return err('用户无法锁定，请重试！');
    }

    $uid = Request::str('uid');
    $data = Request::array('data');
    if (empty($data)) {
        return err('提交的数据为空！');
    }

    $result = Task::submit($uid, $data, $user);

    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('提交成功！');
}