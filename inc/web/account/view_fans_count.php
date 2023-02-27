<?php

namespace zovye;

$id = request::int('id');
$acc = Account::get($id);

if (empty($acc)) {
    JSON::fail('找不到这个任务！');
}

$query = Order::query(['account' => $acc->getName()]);

$num = (int)$query->get('count(DISTINCT `openid`)');

JSON::success("{$acc->getTitle()}，净增粉丝总数：{$num}人");