<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\accountModelObj;

$query = Account::query(['type' => Account::FlashEgg]);

$list = [];

/** @var accountModelObj $account */
foreach($query->findAll() as $account) {
    $data = [
        'id' => $account->getId(),
        'title' => $account->getTitle(),
        'descr' => html_entity_decode($account->getDescription()),
        'img' => $account->isThirdPartyPlatform() || $account->isDouyin() ? $account->getImg() : Util::toMedia($account->getImg(), true),
        'createtime_formatted' => date('Y-m-d H:i:s', $account->getCreatetime()),
    ];

    $agent = $account->getAgent();
    if ($agent) {
        $data['agent'] = $agent->profile();
    }
    
    $list[] = $data;
}

$content = app()->fetchTemplate(
    'web/account/flash_egg_list',
    [
        'list' => $list,
    ]
);

JSON::success(['title' => '请选择闪蛋商品', 'content' => $content]);