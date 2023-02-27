<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\agent_appModelObj;

$page = max(1, request::int('page'));
$page_size = request::int('pagesize', 10);

$query = m('agent_app')->where(We7::uniacid([]));

$total = $query->count();

$pager = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id desc');

$apps = [];

/** @var agent_appModelObj $entry */
foreach ($query->findAll() as $entry) {
    $apps[] = [
        'id' => $entry->getId(),
        'name' => $entry->getName(),
        'mobile' => $entry->getMobile(),
        'address' => $entry->getAddress(),
        'referee' => $entry->getReferee(),
        'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        'state' => $entry->getState(),
    ];
}

app()->showTemplate('web/agent/app', [
    'op' => 'app',
    'pager' => $pager,
    'apps' => $apps,
]);