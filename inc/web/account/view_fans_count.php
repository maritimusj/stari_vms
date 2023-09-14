<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;
use zovye\domain\Order;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$account = Account::get($id);

if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

$query = Order::query(['account' => $account->getName()]);

$num = (int)$query->get('count(DISTINCT `openid`)');

JSON::success("{$account->getTitle()}，净增粉丝总数：{$num}人");