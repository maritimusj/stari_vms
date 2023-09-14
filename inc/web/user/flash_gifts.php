<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\business\FlashEgg;
use zovye\domain\User;
use zovye\model\giftModelObj;

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