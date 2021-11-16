<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\prizeModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        Util::resultAlert('找不到用户！', 'error');
    }

    $tpl_data = Util::getTplData(
        [
            $user,
            [
                'page.title' => '用户中心',
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
    }

    app()->userCenterPage($tpl_data);
}