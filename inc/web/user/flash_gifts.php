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
$gift = FlashEgg::getUserActiveGift($user);
if ($gift) {
    $list[] = FlashEgg::getUserGiftDetail($user, $gift);
}

Response::templateJSON('web/account/gift_data',
    "{$user->getNickname()} - 正在参加的集蛋活动",
    [
    'list' => $list,
]);