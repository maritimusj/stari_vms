<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$account = Account::get(Request::int('id'));
if (empty($account) || !$account->isQuestionnaire()) {
    JSON::fail('找不到这个问卷任务！');
}

$content = app()->fetchTemplate(
    'web/questionnaire/export',
    [
        'account' => $account->profile(),
    ]
);

JSON::success(['title' => '导出', 'content' => $content]);