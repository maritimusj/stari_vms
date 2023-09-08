<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$principal = Request::trim('principal');

if ($principal == 'agent') {
    $query = Principal::agent();
} elseif ($principal == 'keeper') {
    $query = Principal::keeper();
} elseif ($principal == 'partner') {
    $query = Principal::partner();
} elseif ($principal == 'tester') {
    $query = Principal::tester();
} elseif ($principal == 'gspor') {
    $query = Principal::gspor();
} else {
    $query = User::query();
}

if (Request::isset('app')) {
    $query->where(['app' => Request::int('app')]);
}

$keywords = Request::trim('keywords');
if (!empty($keywords)) {
    $query->whereOr(
        [
            'nickname LIKE' => "%$keywords%",
            'mobile LIKE' => "%$keywords%",
        ]
    );
}

$total = $query->count();
$total_page = ceil($total / $page_size);

$result = [
    'page' => $page,
    'pagesize' => $page_size,
    'totalpage' => $total_page,
    'total' => $total,
    'list' => [],
];

if ($total > 0) {
    $query->orderBy('id DESC');
    $query->page($page, $page_size);

    /** @var userModelObj $user */
    foreach ($query->findAll() as $user) {
        $result['list'][] = [
            'id' => $user->getId(),
            'openid' => $user->getOpenid(),
            'nickname' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'mobile' => $user->getMobile(),
        ];
    }
}

JSON::data($result);
