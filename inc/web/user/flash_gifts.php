<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\giftModelObj;

defined('IN_IA') or exit('Access Denied');

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$list = [];

/** @var giftModelObj $gift */
foreach (FlashEgg::getCollectingGiftList($user) as $gift) {
    $list[] = FlashEgg::getUserGiftDetail($user, $gift);
}

$content = app()->fetchTemplate('web/account/gift_data', [
    'list' => $list,
]);

JSON::success(['title' => '物流信息', 'content' => $content]);