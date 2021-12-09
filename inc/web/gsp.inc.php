<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'search') {
    $s_keyword = request::trim('keyword');

    $query = Principal::gspsor();
    if ($s_keyword) {
        $query->whereOr([
            'name REGEXP' => $s_keyword,
            'nickname REGEXP' => $s_keyword,
            'mobile REGEXP' => $s_keyword,
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

}