<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\userModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = User::query();

$keywords = Request::trim('keywords');
if (!empty($keywords)) {
    $query->whereOr(
        [
            'nickname LIKE' => "%$keywords%",
            'mobile LIKE' => "%$keywords%",
        ]
    );
}

$passport = Request::trim('passport');
if (!empty($passport)) {
    $query->where(
        [
            'passport LIKE' => "%$passport%",
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
    $query->orderBy('id desc');
    $query->page($page, $page_size);

    /** @var userModelObj $user */
    foreach ($query->findAll() as $user) {
        $result['list'][] = [
            'id' => $user->getId(),
            'nickname' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'mobile' => $user->getMobile(),
        ];
    }
}

exit(json_encode($result));