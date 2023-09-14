<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Keeper;
use zovye\domain\Principal;
use zovye\model\userModelObj;

if (!App::isPromoterEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

$query = Principal::promoter(['superior_id' => $keeper->getId()]);

$list = [];

/** @var userModelObj $promoter */
foreach ($query->findAll() as $promoter) {
    $data = [
        'user' => $promoter->profile(false),
    ];
    $data['commission_total'] = $promoter->getCommissionBalance()->total();
    $data['createtime_formatted'] = date('Y-m-d H:i:s', $promoter->getCreatetime());
    $list[] = $data;
}

Response::templateJSON(
    'web/user/promoter_list',
    '全部推广员',
    [
        'id' => $keeper->getId(),
        'list' => $list,
    ]
);