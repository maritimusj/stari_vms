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
    Response::toast('找不到这个用户！', $this->createWebUrl('user'), 'error');
}

if (!$user->isTester()) {
    Response::toast('用户不是测试员！', $this->createWebUrl('user'), 'error');
}

if ($user->setTester(false)) {
    Response::toast('成功！', $this->createWebUrl('user'), 'success');
}

Response::toast('失败！', $this->createWebUrl('user'), 'error');