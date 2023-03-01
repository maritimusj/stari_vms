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

Account::logQuery($acc)->delete();

Util::itoast('已清除所有请求日志！', $this->createWebUrl('account', ['op' => 'viewQueryLog', 'id' => $id]), 'success');