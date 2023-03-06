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
    $lucky = FlashEgg::getLucky($id);
}

if (empty($lucky)) {
    Util::resultAlert('找不到这个抽奖活动！');
}

$tpl_data['id'] = $id;
$tpl_data['agent'] = $lucky->getAgent();
$tpl_data['lucky'] = $lucky->profile(true);

$content = app()->fetchTemplate('web/account/lucky_qrcodes', $tpl_data);

JSON::success(['title' => "抽奖活动二维码", 'content' => $content]);