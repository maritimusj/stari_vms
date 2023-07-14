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
    $lucky = FlashEgg::getLucky($id);
    if (empty($lucky)) {
        Response::alert('找不到这个抽奖活动！');
    }

    $tpl_data['id'] = $id;
    $tpl_data['agent'] = $lucky->getAgent();
    $tpl_data['lucky'] = $lucky;
}

Response::showTemplate('web/account/lucky_edit', $tpl_data);