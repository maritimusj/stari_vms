<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\JSON;
use zovye\Account;
use zovye\request;

if (request::has('uid')) {
    $uid = request::str('uid');
    $acc = Account::findOneFromUID($uid);
} else {
    $id = request::int('id');
    $acc = Account::get($id);
}

if (empty($acc)) {
    JSON::fail('找不到这个任务！');
}

JSON::success($acc->profile());