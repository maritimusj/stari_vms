<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\prizeModelObj;

defined('IN_IA') or exit('Access Denied');

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    Util::resultAlert('找不到用户！', 'error');
}

$tpl_data = Util::getTplData(
    [
        $user,
        [
            'page.title' => '我的奖品',
            'prize' => [
                'enabled' => false,
                'news' => [],
            ],
        ],
    ]
);

if (App::isUserPrizeEnabled()) {

    $tpl_data['prize']['enabled'] = true;
    $tpl_data['prize']['news'] = [];

    $query = m('prize')->where(We7::uniacid([]))->orderBy('createtime desc')->limit(10);
    /** @var prizeModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $x = User::get($entry->getOpenid(), true);
        if ($x) {
            $tpl_data['prize']['news'][] = "<b>{$x->getNickname()}</b>抽中{$entry->getTitle()}！";
        }
    }

    $query->where(
        [
            'openid' => $user->getOpenid(),
        ]
    )->limit(50);

    /** @var prizeModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $x = User::get($entry->getOpenid(), true);
        if ($x) {
            $tpl_data['prize']['list'][] = [
                'title' => $entry->getTitle(),
                'desc' => $entry->getDesc(),
                'link' => $entry->getLink(),
                'img' => Util::tomedia($entry->getImg()),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];
        }
    }
}

app()->myPrizesPage($tpl_data);
