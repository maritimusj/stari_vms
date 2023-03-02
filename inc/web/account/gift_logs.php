<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

$id = request::int('id');
if ($id > 0) {
    $gift = FlashEgg::getGift($id);
    if (empty($gift)) {
        Util::resultAlert('找不到这个活动！');
    }

    $tpl_data['id'] = $id;
    $tpl_data['agent'] = $gift->getAgent();
    $tpl_data['gift'] = $gift;
    $tpl_data['goods_list'] = $gift->getGoodsList(true);
}

app()->showTemplate('web/account/gift_logs', $tpl_data);