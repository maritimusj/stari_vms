<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\gift_logModelObj;

$tpl_data = [];

$query = FlashEgg::giftLogQuery();

$id = Request::int('id');
if ($id > 0) {
    $gift = FlashEgg::getGift($id);
    if (empty($gift)) {
        Util::resultAlert('找不到这个活动！', 'error');
    }

    $tpl_data['gift'] = $gift->profile(true);
    $query->where(['gift_id' => $id]);
}

$user_id = Request::int('user_id');
if ($user_id > 0) {
    $user = User::get($user_id);
    if (empty($user)) {
        Util::resultAlert(' 找不到这个用户！', 'error');
    }
    $tpl_data['user'] = $user->profile(false);
    $query->where(['user_id' => $user->getId()]);
}

if (Request::has('keywords')) {
    $keywords = Request::trim('keywords');
    if ($keywords) {
        $tpl_data['keywords'] = $keywords;
        $query->where(['phone_num LIKE' => "%$keywords%"]);
    }
}

$total = $query->count();

$list = [];
if ($total > 0) {
    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    /** @var gift_logModelObj $log */
    foreach ($query->findAll() as $log) {
        $list[] = $log->format(true);
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
}

$tpl_data['list'] = $list;
$tpl_data['search_url'] = Util::url('account', ['op' => 'gift_logs']);
app()->showTemplate('web/account/gift_logs', $tpl_data);