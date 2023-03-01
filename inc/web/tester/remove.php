<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

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