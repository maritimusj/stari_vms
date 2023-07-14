<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$account_id = Request::int('id');
$account = Account::get($account_id);

if (empty($account)) {
    Response::toast('找不到这个任务！', '', 'error');
}

Response::showTemplate('web/account/stats_view', [
    'account' => $account,
]);