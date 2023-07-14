<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (Request::has('uid')) {
    $uid = Request::str('uid');
    $acc = Account::findOneFromUID($uid);
} else {
    $id = Request::int('id');
    $acc = Account::get($id);
}

if (empty($acc)) {
    JSON::fail('找不到这个任务！');
}

JSON::success($acc->profile());