<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\gift_logModelObj;
use zovye\model\lucky_logModelObj;

if (!App::isFlashEggEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$user = Session::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法领取');
}

$fn = Request::trim('fn');

if ($fn == 'gift_logs') {

    app()->giftLogsPage([
        'user' => $user,
    ]);

} elseif ($fn == 'lucky_logs') {

    app()->luckyLogsPage([
        'user' => $user,
    ]);

} elseif ($fn == 'get_gift_logs') {

    $query = FlashEgg::giftLogQuery([
        'user_id' => $user->getId(),
    ]);

    $total = $query->count();

    $list = [];
    if ($total > 0) {
        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        /** @var gift_logModelObj $log */
        foreach ($query->findAll() as $log) {
            $data = $log->format(true);
            unset($data['delivery']['memo']);
            $list[] = $data;
        }
    }

    JSON::success($list);

} elseif ($fn == 'get_lucky_logs') {

    $query = FlashEgg::luckyLogQuery([
        'user_id' => $user->getId(),
    ]);

    $total = $query->count();

    $list = [];
    if ($total > 0) {
        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        /** @var lucky_logModelObj $log */
        foreach ($query->findAll() as $log) {
            $data = $log->format(true);
            unset($data['delivery']['memo']);
            $list[] = $data;
        }
    }

    JSON::success($list);
}

app()->flashEggPage([
    'user' => $user,
]);