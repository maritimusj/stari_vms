<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;

$result = [];

$query = Device::query();

$openid = Request::trim('openid', '', true);
if ($openid) {
    $agent = Agent::get($openid, true);
    if ($agent) {
        $query->where(['agent_id' => $agent->getId()]);
    }
}

$keyword = Request::trim('keyword', '', true);
if ($keyword) {
    $query->whereOr([
        'imei LIKE' => "%$keyword%",
        'name LIKE' => "%$keyword%",
    ]);
}

if (Request::has('page')) {
    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query->page($page, $page_size);
} else {
    $query->limit(20);
}

/** @var deviceModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = [
        'id' => $entry->getId(),
        'imei' => $entry->getImei(),
        'name' => $entry->getName(),
    ];

    $res = $entry->getAgent();
    if ($res) {
        $data['agent'] = $res->getName();
    }

    $result[] = $data;
}

JSON::success($result);