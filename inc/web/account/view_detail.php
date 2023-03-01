<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$log = Questionnaire::log(['id' => $id])->findOne();

if (empty($log)) {
    JSON::fail('找不到这个问卷提交记录！');
}

$questions = $log->getData('questions', []);
$answer = $log->getData('answer', []);
$result = $log->getData('result.stats', []);
$account = $log->getData('account', []);

$content = app()->fetchTemplate(
    'web/account/questionnaire_detail',
    [
        'questions' => $questions,
        'answer' => $answer,
        'result' => $result,
        'account' => $account,
    ]
);

JSON::success(['title' => '问卷提交详情', 'content' => $content]);