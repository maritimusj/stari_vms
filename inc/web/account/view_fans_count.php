<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$acc = Account::get($id);

if (empty($acc)) {
    JSON::fail('找不到这个任务！');
}

$query = Order::query(['account' => $acc->getName()]);

$num = (int)$query->get('count(DISTINCT `openid`)');

JSON::success("{$acc->getTitle()}，净增粉丝总数：{$num}人");