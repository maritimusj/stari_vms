<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\couponModelObj;

defined('IN_IA') or exit('Access Denied');

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    Util::message('找不到用户！');
}

$tpl_data = Util::getTplData(
    [
        $user,
        ['coupon' => []],
    ]
);

$tpl_data['amount']['list'] = [
    ['amount' => 5],
    ['amount' => 10],
    ['amount' => 20],
    ['amount' => 30],
    ['amount' => 50],
    ['amount' => 100],
];

foreach ($tpl_data['amount']['list'] as &$entry) {
    if (!empty($entry['amount']) && empty($entry['price'])) {
        $entry['price'] = $tpl_data['balance']['price'] * $entry['amount'];
    }
    $entry['price_formatted'] = number_format($entry['price'] / 100, 2);
}

$now = time();

$query = m('coupon')->query();
$query->where(We7::uniacid(['owner' => $user->getOpenid()]));
$query->where("(used_time IS NULL OR used_time=0) AND (expired_time IS NULL OR expired_time>{$now})");
$query->groupBy('title');

/** @var couponModelObj $i */
foreach ($query->findAll() as $i) {
    $tpl_data['coupon']['list'][] = [
        'uid' => $i->getUid(),
        'title' => $i->getTitle(),
        'memo' => $i->getMemo(),
        'xrequire' => $i->getXrequire() * 100,
    ];
}

app()->chargePage($tpl_data);
