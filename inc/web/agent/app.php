<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\agent_appModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 10);

$query = AgentApplication::model()->where(We7::uniacid([]));

$total = $query->count();

$pager = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id DESC');

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

Response::showTemplate('web/agent/app', [
    'op' => 'app',
    'pager' => $pager,
    'apps' => $apps,
]);