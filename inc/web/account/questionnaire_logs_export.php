<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$account = Account::get(Request::int('id'));
if (empty($account) || !$account->isQuestionnaire()) {
    JSON::fail('找不到这个问卷任务！');
}

$s_date = Request::str('s_date');
$e_date = Request::str('e_date');

$result = Questionnaire::exportLogs($account, $s_date, $e_date);
JSON::result($result);