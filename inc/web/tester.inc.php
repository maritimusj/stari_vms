<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'create') {

    $id = request::int('id');

    $user = User::get($id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', $this->createWebUrl('user'), 'error');
    }

    if ($user->isTester()) {
        Util::itoast('用户已经是测试员！', $this->createWebUrl('user'), 'error');
    }

    if ($user->setTester()) {
        Util::itoast('成功！', $this->createWebUrl('user'), 'success');
    }

    Util::itoast('失败！', $this->createWebUrl('user'), 'error');

} elseif ($op == 'remove') {

    $id = request::int('id');

    $user = User::get($id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', $this->createWebUrl('user'), 'error');
    }

    if (!$user->isTester()) {
        Util::itoast('用户不是测试员！', $this->createWebUrl('user'), 'error');
    }

    if ($user->setTester(false)) {
        Util::itoast('成功！', $this->createWebUrl('user'), 'success');
    }
    
    Util::itoast('失败！', $this->createWebUrl('user'), 'error');
}