<?php

namespace zovye;

$account = Account::get(request::int('id'));
if (empty($account) || !$account->isQuestionnaire()) {
    JSON::fail('找不到这个问卷任务！');
}

$s_date = request::str('s_date');
$e_date = request::str('e_date');

$result = Questionnaire::exportLogs($account, $s_date, $e_date);
JSON::result($result);