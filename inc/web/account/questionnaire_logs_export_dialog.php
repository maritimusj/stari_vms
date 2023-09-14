<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;

defined('IN_IA') or exit('Access Denied');

$account = Account::get(Request::int('id'));
if (empty($account) || !$account->isQuestionnaire()) {
    JSON::fail('找不到这个问卷任务！');
}

Response::templateJSON(
    'web/questionnaire/export',
    '导出',
    [
        'account' => $account->profile(),
    ]
);
