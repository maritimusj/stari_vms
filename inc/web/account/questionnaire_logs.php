<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('account');
$account = Account::get($id);
if (empty($account) || !$account->isQuestionnaire()) {
    Util::resultAlert('找不到这个问卷！', 'error');
}

$query = $account->logQuery(['level' => $account->getId()]);
$total = $query->count();

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$pager = We7::pagination($total, $page, $page_size);

$answers = [];
if ($total > 0) {
    $query->page($page, $page_size);
    $query->orderBy('id DESC');
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'user' => $entry->getData('user', []),
            'result' => $entry->getData('result', []),
            'device' => $entry->getData('device', []),
            'order' => $entry->getData('order'),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
        $total = count($entry->getData('questions', []));
        if ($total > 0) {
            $data['total'] = $total;
            $data['percent'] = intval((floatval($data['result']['num']) / floatval($total)) * 100);
        }
        $answers[] = $data;
    }
}

app()->showTemplate('web/account/questionnaire_logs', [
    'account' => $account->profile(),
    'list' => $answers,
    'pager' => $pager,
]);