<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\deviceModelObj;

$result = [];

$query = Device::query();

$openid = request::trim('openid', '', true);
if ($openid) {
    $agent = Agent::get($openid, true);
    if ($agent) {
        $query->where(['agent_id' => $agent->getId()]);
    }
}

$keyword = request::trim('keyword', '', true);
if ($keyword) {
    $query->whereOr([
        'imei LIKE' => "%$keyword%",
        'name LIKE' => "%$keyword%",
    ]);
}

if (request::has('page')) {
    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

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