<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

$id = Request::int('id');

if ($id > 0) {
    $gift = FlashEgg::getGift($id);
    if (empty($gift)) {
        Response::alert('找不到这个活动！');
    }

    $tpl_data['id'] = $id;
    $tpl_data['agent'] = $gift->getAgent();
    $tpl_data['gift'] = $gift;
    $tpl_data['goods_list'] = $gift->getGoodsList(true);
}

Response::showTemplate('web/account/gift_edit', $tpl_data);