<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\userModelObj;

$query = Principal::agent();
$id = request::int('id');
if ($id) {
    $query->where(['id <>' => $id]);
}

$openid = request::str('openid', '', true);
if ($openid) {
    $query->where(['openid' => $openid]);
}

$keyword = request::str('keyword', '', true);
if ($keyword) {
    $query->whereOr([
        'name REGEXP' => $keyword,
        'nickname REGEXP' => $keyword,
        'mobile REGEXP' => $keyword,
    ]);
}

$query->limit(20);

$result = [];
/** @var  userModelObj $entry */
foreach ($query->findAll() as $entry) {
    $result[] = [
        'id' => $entry->getId(),
        'openid' => $entry->getOpenid(),
        'nickname' => $entry->getNickname(),
        'name' => $entry->getName(),
        'company' => $entry->settings('agentData.company', '未登记'),
        'mobile' => $entry->getMobile(),
        'avatar' => $entry->getAvatar(),
    ];
}

JSON::success($result);