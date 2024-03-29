<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Principal;
use zovye\model\gspor_vwModelObj;

$query = Principal::gspor();

$s_keyword = Request::trim('keyword');
if ($s_keyword) {
    $query->whereOr([
        'name REGEXP' => $s_keyword,
        'nickname REGEXP' => $s_keyword,
        'mobile REGEXP' => $s_keyword,
    ]);
}

$query->limit(20);

$result = [];
/** @var  gspor_vwModelObj $entry */
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