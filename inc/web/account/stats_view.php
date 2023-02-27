<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$account_id = request::int('id');
$account = Account::get($account_id);

if (empty($account)) {
    Util::itoast('找不到这个任务！', '', 'error');
}

app()->showTemplate('web/account/stats_view', [
    'account' => $account,
]);