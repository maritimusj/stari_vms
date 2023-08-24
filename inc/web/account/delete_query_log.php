<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$account = Account::get($id);
if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

Account::logQuery($account)->delete();

Response::toast('已清除所有请求日志！', Util::url('account', ['op' => 'viewQueryLog', 'id' => $id]), 'success');