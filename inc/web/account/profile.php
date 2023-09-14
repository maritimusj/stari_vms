<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;

defined('IN_IA') or exit('Access Denied');

if (Request::has('uid')) {
    $uid = Request::str('uid');
    $account = Account::findOneFromUID($uid);
} else {
    $id = Request::int('id');
    $account = Account::get($id);
}

if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

JSON::success($account->profile());